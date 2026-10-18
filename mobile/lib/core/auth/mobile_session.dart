class MobileSession {
  const MobileSession({
    required this.token,
    required this.name,
    required this.canMark,
    required this.canVisit,
  });

  final String token;
  final String name;
  final bool canMark;
  final bool canVisit;

  factory MobileSession.fromLogin(Map<String, dynamic> json) {
    final user = json['usuario'] as Map<String, dynamic>;
    final accesses = user['accesos'] as Map<String, dynamic>;

    return MobileSession(
      token: json['token'] as String,
      name: user['nombre'] as String,
      canMark: accesses['marcacion'] == true,
      canVisit: accesses['visitas'] == true,
    );
  }
}
