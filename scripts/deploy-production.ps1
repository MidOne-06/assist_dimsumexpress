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

$remoteCommand = @"
set -eu
cd '$ProductionPath'
tar -xf -
printf '%s' '$resolvedCommit' > storage/app/.release-sha
docker compose build app --quiet
docker compose run --rm app php artisan migrate --force
docker compose up -d --no-deps --force-recreate app worker scheduler
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T -u root app sh -lc 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'
curl -fsS -o /dev/null https://assist.dimsumexpress.cloud/admin/login
printf 'DEPLOYED_SHA=%s\\n' "`$(cat storage/app/.release-sha)"
"@

git archive --format=tar $resolvedCommit | ssh $ProductionHost $remoteCommand
if ($LASTEXITCODE -ne 0) {
    throw "El despliegue del commit $resolvedCommit no finalizó correctamente."
}
