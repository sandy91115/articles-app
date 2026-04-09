import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'api_client.dart';
import 'models.dart';
import 'reader_palette.dart';
import 'screens/login_screen.dart';
import 'screens/shell_screen.dart';

void runReaderApp() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
    systemNavigationBarColor: ReaderPalette.canvas,
    systemNavigationBarIconBrightness: Brightness.dark,
  ));

  runApp(const ReaderApp());
}

class ReaderApp extends StatefulWidget {
  const ReaderApp({super.key});

  @override
  State<ReaderApp> createState() => _ReaderAppState();
}

class _ReaderAppState extends State<ReaderApp> {
  final ReaderApiClient _apiClient = ReaderApiClient();
  ReaderSession? _session;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Article Reader',
      debugShowCheckedModeBanner: false,
      theme: _buildTheme(),
      home: _session == null
          ? ReaderLoginScreen(
              suggestedBaseUrl: _suggestBaseUrl(),
              onLoggedIn: (session) {
                setState(() {
                  _session = session;
                });
              },
              apiClient: _apiClient,
            )
          : ReaderShellScreen(
              apiClient: _apiClient,
              session: _session!,
              onLoggedOut: () {
                setState(() {
                  _session = null;
                });
              },
            ),
    );
  }

  ThemeData _buildTheme() {
    final base = ThemeData.light(useMaterial3: true);

    return base.copyWith(
      scaffoldBackgroundColor: ReaderPalette.canvas,
      colorScheme: base.colorScheme.copyWith(
        brightness: Brightness.light,
        primary: ReaderPalette.primary,
        secondary: ReaderPalette.secondary,
        surface: ReaderPalette.surface,
        onPrimary: Colors.white,
        onSecondary: ReaderPalette.ink,
        onSurface: ReaderPalette.ink,
      ),
      textTheme: base.textTheme.apply(
        bodyColor: ReaderPalette.ink,
        displayColor: ReaderPalette.ink,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: ReaderPalette.ink,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: ReaderPalette.surface,
        hintStyle: const TextStyle(color: ReaderPalette.inkMuted),
        labelStyle: const TextStyle(color: ReaderPalette.inkMuted),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: const BorderSide(color: ReaderPalette.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: const BorderSide(color: ReaderPalette.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(20),
          borderSide: const BorderSide(color: ReaderPalette.primary, width: 1.2),
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
      ),
      cardTheme: CardThemeData(
        color: ReaderPalette.surface,
        elevation: 0.5,
        shadowColor: ReaderPalette.shadow,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(28),
          side: const BorderSide(color: ReaderPalette.border),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: ReaderPalette.primary,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: ReaderPalette.primary,
          side: const BorderSide(color: ReaderPalette.border),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: ReaderPalette.primary,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: ReaderPalette.primary,
      ),
    );
  }

  String _suggestBaseUrl() {
    try {
      if (Platform.isAndroid) {
        return 'http://10.0.2.2:8000';
      }
    } catch (_) {
      // Keep the desktop fallback below.
    }

    return 'http://127.0.0.1:8000';
  }
}
