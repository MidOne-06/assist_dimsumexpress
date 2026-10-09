[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $Commit,

    [string] $ProductionHost = 'root@2.25.155.29',

    [string] $ProductionPath = '/opt/asistencias'
)

$ErrorActionPreference = 'Stop'

$resolvedCommit = (git rev-parse "$Commit^{commit}").Trim()
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($resolvedCommit)) {
    throw "No se pudo resolver el commit '$Commit'."
}

git diff --quiet $resolvedCommit --
if ($LASTEXITCODE -ne 0) {
    throw 'El árbol de trabajo contiene cambios fuera del commit que se va a desplegar.'
}

# Docker conserva capas por diseño. Pasar el hash del lock obliga a reconstruir
# vendor cuando cambia una dependencia, aun si el builder remoto mantiene caché.
$composerLockHash = (git rev-parse "$resolvedCommit`:composer.lock").Trim()
if ([string]::IsNullOrWhiteSpace($composerLockHash)) {
    throw "No se pudo calcular la huella de composer.lock para $resolvedCommit."
}

$archivePath = Join-Path ([System.IO.Path]::GetTempPath()) "asistencias-$resolvedCommit.tar"
$remoteArchive = "/tmp/asistencias-$resolvedCommit.tar"
$releaseRoot = "$ProductionPath-releases"
$releasePath = "$releaseRoot/$resolvedCommit"

$remoteCommand = @"
set -eu
current_path='$ProductionPath'
release_root='$releaseRoot'
release_path='$releasePath'
legacy_path="$ProductionPath.legacy-`$(date +%Y%m%d%H%M%S)"
mkdir -p "`$release_root"
test ! -e "`$release_path" || { echo 'El release ya existe: '"`$release_path" >&2; exit 1; }
mkdir "`$release_path"
trap 'rm -f "$remoteArchive"' EXIT
tar -xf '$remoteArchive' -C "`$release_path"

# Configuración del host, volúmenes y datos no viajan dentro del artefacto.
for config in .env .env.docker; do
  if [ -f "`$current_path/`$config" ]; then
    cp -p "`$current_path/`$config" "`$release_path/`$config"
  fi
done

cd "`$release_path"
docker compose -p asistencias build --build-arg COMPOSER_LOCK_SHA='$composerLockHash' --build-arg RELEASE_SHA='$resolvedCommit' app --quiet
docker compose -p asistencias run --rm app php artisan migrate --force
docker compose -p asistencias run --rm app php artisan db:seed --class=RolesYPermisosSeeder --force
docker compose -p asistencias up -d --no-deps --force-recreate app worker scheduler web
docker compose -p asistencias exec -T -u root app sh -lc 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'
docker compose -p asistencias exec -T -u www-data app php artisan optimize:clear
docker compose -p asistencias exec -T -u www-data app php artisan config:cache
docker compose -p asistencias exec -T -u www-data app php artisan route:cache
docker compose -p asistencias exec -T -u www-data app php artisan view:cache
curl -fsS -o /dev/null https://assist.dimsumexpress.cloud/admin/login
curl -fsS -o /dev/null https://assist.dimsumexpress.cloud/health

# El directorio activo solo cambia cuando el release nuevo ya está atendiendo.
if [ -L "`$current_path" ]; then
  rm "`$current_path"
else
  mv "`$current_path" "`$legacy_path"
fi
ln -s "`$release_path" "`$current_path"
printf '%s' '$resolvedCommit' > storage/app/.release-sha
printf 'DEPLOYED_SHA=%s\\n' "`$(cat storage/app/.release-sha)"
"@

try {
    # PowerShell 5 puede corromper un flujo binario entre procesos nativos.
    # El archivo temporal + SCP conserva el tar generado por Git intacto.
    git archive --format=tar --output=$archivePath $resolvedCommit
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo preparar el artefacto del commit $resolvedCommit."
    }

    scp $archivePath "${ProductionHost}:$remoteArchive"
    if ($LASTEXITCODE -ne 0) {
        throw "No se pudo transferir el artefacto del commit $resolvedCommit."
    }

    ssh $ProductionHost $remoteCommand
    if ($LASTEXITCODE -ne 0) {
        throw "El despliegue del commit $resolvedCommit no finalizó correctamente."
    }
} finally {
    Remove-Item -LiteralPath $archivePath -Force -ErrorAction SilentlyContinue
}
