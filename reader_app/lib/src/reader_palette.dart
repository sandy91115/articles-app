import 'package:flutter/material.dart';

class ReaderPalette {
  static const Color primary = Color(0xFF003973);
  static const Color primarySoft = Color(0xFF4E7294);
  static const Color secondary = Color(0xFFE5E5BE);
  static const Color canvas = Color(0xFFF3F1ED);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surfaceMuted = Color(0xFFF8F6F1);
  static const Color border = Color(0xFFE7E2DA);
  static const Color ink = Color(0xFF141826);
  static const Color inkMuted = Color(0xFF7B8090);
  static const Color inverseText = Color(0xFFFCFCFA);
  static const Color inverseMuted = Color(0xFFE5E8F0);
  static const Color accent = Color(0xFF5A8DEE);
  static const Color success = Color(0xFF2E7D60);
  static const Color warning = Color(0xFFAF7B2C);
  static const Color darkSurface = Color(0xFF111318);
  static const Color darkSurfaceSoft = Color(0xFF1B1F27);
  static const Color shadow = Color(0x140B1220);

  static const LinearGradient editorialGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    stops: [0.0, 0.62, 1.0],
    colors: [primary, primarySoft, secondary],
  );

  static const LinearGradient surfaceGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [surface, canvas],
  );

  static const LinearGradient navGradient = LinearGradient(
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
    colors: [primary, primarySoft],
  );

  static const List<BoxShadow> softShadow = [
    BoxShadow(color: shadow, blurRadius: 30, offset: Offset(0, 14)),
  ];
}
