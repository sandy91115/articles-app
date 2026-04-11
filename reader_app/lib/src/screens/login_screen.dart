import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../api_client.dart';
import '../models.dart';
import '../reader_palette.dart';

enum _AuthStage { login, signup, verify }

class ReaderLoginScreen extends StatefulWidget {
  const ReaderLoginScreen({
    super.key,
    required this.suggestedBaseUrl,
    required this.onLoggedIn,
    required this.apiClient,
  });

  final String suggestedBaseUrl;
  final ValueChanged<ReaderSession> onLoggedIn;
  final ReaderApiClient apiClient;

  @override
  State<ReaderLoginScreen> createState() => _ReaderLoginScreenState();
}

class _ReaderLoginScreenState extends State<ReaderLoginScreen> {
  late final TextEditingController _emailController;
  late final TextEditingController _passwordController;
  late final TextEditingController _baseUrlController;
  late final TextEditingController _signupNameController;
  late final TextEditingController _signupEmailController;
  late final TextEditingController _signupPhoneController;
  late final TextEditingController _signupUsernameController;
  late final TextEditingController _signupPasswordController;
  late final TextEditingController _signupConfirmPasswordController;
  late final TextEditingController _otpEmailController;
  late final TextEditingController _otpCodeController;
  _AuthStage _stage = _AuthStage.login;
  bool _busy = false;
  bool _otpRequested = false;
  String? _error;
  String? _notice;
  String? _debugCode;

  @override
  void initState() {
    super.initState();
    _emailController = TextEditingController(text: 'aarav.mehta@example.com');
    _passwordController = TextEditingController(text: 'password');
    _baseUrlController = TextEditingController(text: widget.suggestedBaseUrl);
    _signupNameController = TextEditingController();
    _signupEmailController = TextEditingController();
    _signupPhoneController = TextEditingController();
    _signupUsernameController = TextEditingController();
    _signupPasswordController = TextEditingController();
    _signupConfirmPasswordController = TextEditingController();
    _otpEmailController = TextEditingController();
    _otpCodeController = TextEditingController();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _baseUrlController.dispose();
    _signupNameController.dispose();
    _signupEmailController.dispose();
    _signupPhoneController.dispose();
    _signupUsernameController.dispose();
    _signupPasswordController.dispose();
    _signupConfirmPasswordController.dispose();
    _otpEmailController.dispose();
    _otpCodeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 24, 20, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildHero(context),
              const SizedBox(height: 24),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(22),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _stageTitle,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        _stageSubtitle,
                        style: const TextStyle(
                          color: ReaderPalette.inkMuted,
                          height: 1.5,
                        ),
                      ),
                      const SizedBox(height: 18),
                      _buildStageSelector(),
                      if (_error != null || _notice != null) ...[
                        const SizedBox(height: 18),
                        _buildStatusBanner(),
                      ],
                      if (_stage == _AuthStage.verify &&
                          _debugCode != null) ...[
                        const SizedBox(height: 14),
                        _buildDebugCodeCard(),
                      ],
                      const SizedBox(height: 20),
                      AnimatedSwitcher(
                        duration: const Duration(milliseconds: 220),
                        switchInCurve: Curves.easeOutCubic,
                        switchOutCurve: Curves.easeInCubic,
                        child: _buildStageBody(),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String get _stageTitle {
    switch (_stage) {
      case _AuthStage.signup:
        return 'Create reader account';
      case _AuthStage.verify:
        return 'Verify email OTP';
      case _AuthStage.login:
        return 'Reader login';
    }
  }

  String get _stageSubtitle {
    switch (_stage) {
      case _AuthStage.signup:
        return 'Set up your reader profile, then confirm the 6-digit OTP from email before the app opens.';
      case _AuthStage.verify:
        return 'Enter the OTP sent to your email. Verification logs you straight into the reader app.';
      case _AuthStage.login:
        return 'Open the latest features, opinion, culture, and long-form articles from your Laravel backend.';
    }
  }

  Widget _buildHero(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: ReaderPalette.editorialGradient,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Daily Reading',
            style: TextStyle(
              fontSize: 14,
              letterSpacing: 1.4,
              color: ReaderPalette.inverseMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            'A calmer home for headlines, features, interviews, and thoughtful reads.',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
              fontWeight: FontWeight.w700,
              height: 1.12,
              color: ReaderPalette.inverseText,
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'Create an account, verify your inbox OTP, and move straight into a polished article-reading experience without touching the browser dashboard.',
            style: TextStyle(
              color: ReaderPalette.inverseMuted,
              height: 1.55,
            ),
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: const [
              _HeroChip(icon: Icons.newspaper_rounded, label: 'Daily stories'),
              _HeroChip(
                icon: Icons.person_add_alt_1_rounded,
                label: 'Reader signup',
              ),
              _HeroChip(
                icon: Icons.mail_outline_rounded,
                label: 'Email OTP',
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildStageSelector() {
    final stages = <_AuthStage>[
      _AuthStage.login,
      _AuthStage.signup,
      if (_otpRequested || _stage == _AuthStage.verify) _AuthStage.verify,
    ];

    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: stages
          .map(
            (stage) => _StagePill(
              label: switch (stage) {
                _AuthStage.login => 'Login',
                _AuthStage.signup => 'Sign up',
                _AuthStage.verify => 'Verify OTP',
              },
              selected: _stage == stage,
              onTap: _busy ? null : () => _switchStage(stage),
            ),
          )
          .toList(growable: false),
    );
  }

  Widget _buildStatusBanner() {
    final isError = _error != null;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isError
            ? const Color(0xFFF5E2DF)
            : const Color(0xFFE8F2EA),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isError
              ? const Color(0xFFD7A49B)
              : const Color(0xFFA8C7B1),
        ),
      ),
      child: Text(
        isError ? _error! : _notice!,
        style: TextStyle(
          color: isError ? const Color(0xFF6F342D) : ReaderPalette.success,
          height: 1.45,
        ),
      ),
    );
  }

  Widget _buildDebugCodeCard() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: ReaderPalette.surfaceMuted,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: ReaderPalette.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Debug OTP',
            style: TextStyle(
              color: ReaderPalette.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            _debugCode!,
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w700,
              letterSpacing: 6,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Visible only while the backend runs with APP_DEBUG enabled.',
            style: TextStyle(color: ReaderPalette.inkMuted, height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _buildStageBody() {
    switch (_stage) {
      case _AuthStage.signup:
        return KeyedSubtree(
          key: const ValueKey<String>('signup'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildTextField(
                controller: _signupNameController,
                label: 'Name',
                hint: 'Reader One',
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _signupEmailController,
                label: 'Email',
                hint: 'reader.one@example.com',
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _signupPhoneController,
                label: 'Phone',
                hint: '9876543210',
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _signupUsernameController,
                label: 'User',
                hint: 'reader.one',
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _signupPasswordController,
                label: 'Password',
                hint: 'Minimum 8 characters',
                obscureText: true,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _signupConfirmPasswordController,
                label: 'Confirm password',
                hint: 'Re-enter your password',
                obscureText: true,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildBaseUrlField(),
              const SizedBox(height: 10),
              const Text(
                'A verification OTP will be sent to this email before the account is activated.',
                style: TextStyle(color: ReaderPalette.inkMuted, height: 1.45),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _busy ? null : _signup,
                  child: Text(_busy ? 'Creating account...' : 'Create account'),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _busy
                      ? null
                      : () => _switchStage(_AuthStage.login),
                  child: const Text('Back to login'),
                ),
              ),
            ],
          ),
        );
      case _AuthStage.verify:
        return KeyedSubtree(
          key: const ValueKey<String>('verify'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildTextField(
                controller: _otpEmailController,
                label: 'Email',
                hint: 'reader.one@example.com',
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _otpCodeController,
                label: 'OTP code',
                hint: '6-digit code',
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.done,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(6),
                ],
              ),
              const SizedBox(height: 14),
              _buildBaseUrlField(),
              const SizedBox(height: 10),
              const Text(
                'Use the code from your inbox. If the account was blocked at login because it is unverified, completing this step opens the app immediately.',
                style: TextStyle(color: ReaderPalette.inkMuted, height: 1.45),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _busy ? null : _verifyOtp,
                  child: Text(_busy ? 'Verifying...' : 'Verify and continue'),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _busy ? null : _resendOtp,
                  child: Text(_busy ? 'Sending OTP...' : 'Resend OTP'),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _busy
                      ? null
                      : () => _switchStage(_AuthStage.login),
                  child: const Text('Back to login'),
                ),
              ),
            ],
          ),
        );
      case _AuthStage.login:
        return KeyedSubtree(
          key: const ValueKey<String>('login'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildTextField(
                controller: _emailController,
                label: 'Email',
                hint: 'aarav.mehta@example.com',
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildTextField(
                controller: _passwordController,
                label: 'Password',
                hint: 'password',
                obscureText: true,
                textInputAction: TextInputAction.next,
              ),
              const SizedBox(height: 14),
              _buildBaseUrlField(),
              const SizedBox(height: 10),
              const Text(
                'Use 10.0.2.2 for the Android emulator. Use 127.0.0.1 when running on the same laptop with desktop or the iOS simulator.',
                style: TextStyle(
                  color: ReaderPalette.inkMuted,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _busy ? null : _login,
                  child: Text(_busy ? 'Opening app...' : 'Open Reader App'),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: _busy
                      ? null
                      : () => _switchStage(_AuthStage.signup),
                  child: const Text('Create reader account'),
                ),
              ),
              const SizedBox(height: 8),
              Center(
                child: TextButton(
                  onPressed: _busy ? null : _openManualVerification,
                  child: const Text('Already have an OTP? Verify email'),
                ),
              ),
            ],
          ),
        );
    }
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required String hint,
    bool obscureText = false,
    TextInputType? keyboardType,
    TextInputAction? textInputAction,
    List<TextInputFormatter>? inputFormatters,
  }) {
    return TextField(
      controller: controller,
      obscureText: obscureText,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      inputFormatters: inputFormatters,
      decoration: InputDecoration(labelText: label, hintText: hint),
    );
  }

  Widget _buildBaseUrlField() {
    return _buildTextField(
      controller: _baseUrlController,
      label: 'Backend URL',
      hint: 'http://10.0.2.2:8000',
      keyboardType: TextInputType.url,
      textInputAction: TextInputAction.done,
    );
  }

  Future<void> _login() async {
    FocusManager.instance.primaryFocus?.unfocus();

    final baseUrl = _baseUrlController.text.trim();
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    if (baseUrl.isEmpty || email.isEmpty || password.isEmpty) {
      _showError('Backend URL, email, and password are required.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
      _debugCode = null;
    });

    try {
      final session = await widget.apiClient.login(
        baseUrl: baseUrl,
        email: email,
        password: password,
      );

      if (!mounted) {
        return;
      }

      widget.onLoggedIn(session);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      if (error.statusCode == 403) {
        _prepareVerification(
          email: email,
          message: error.message,
          debugCode: error.debugCode,
        );
      } else {
        _showError(error.message);
      }
    } catch (_) {
      if (!mounted) {
        return;
      }

      _showError(
        'Unable to reach the backend. Check the URL and ensure Laravel is running.',
      );
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _signup() async {
    FocusManager.instance.primaryFocus?.unfocus();

    final baseUrl = _baseUrlController.text.trim();
    final name = _signupNameController.text.trim();
    final email = _signupEmailController.text.trim();
    final phone = _signupPhoneController.text.trim();
    final username = _signupUsernameController.text.trim();
    final password = _signupPasswordController.text;
    final confirmPassword = _signupConfirmPasswordController.text;

    if (baseUrl.isEmpty ||
        name.isEmpty ||
        email.isEmpty ||
        phone.isEmpty ||
        username.isEmpty ||
        password.isEmpty ||
        confirmPassword.isEmpty) {
      _showError(
        'Name, email, phone, user, password, confirm password, and backend URL are required.',
      );
      return;
    }

    if (password != confirmPassword) {
      _showError('Password and confirm password must match.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
      _debugCode = null;
    });

    try {
      final result = await widget.apiClient.register(
        baseUrl: baseUrl,
        name: name,
        email: email,
        phone: phone,
        username: username,
        password: password,
        confirmPassword: confirmPassword,
      );

      if (!mounted) {
        return;
      }

      _emailController.text = email;
      _passwordController.text = password;
      _prepareVerification(
        email: result.email,
        message: result.message,
        debugCode: result.debugCode,
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      _showError(error.message);
    } catch (_) {
      if (!mounted) {
        return;
      }

      _showError(
        'Unable to reach the backend. Check the URL and ensure Laravel is running.',
      );
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _verifyOtp() async {
    FocusManager.instance.primaryFocus?.unfocus();

    final baseUrl = _baseUrlController.text.trim();
    final email = _otpEmailController.text.trim();
    final code = _otpCodeController.text.trim();

    if (baseUrl.isEmpty || email.isEmpty || code.isEmpty) {
      _showError('Backend URL, email, and OTP code are required.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });

    try {
      final session = await widget.apiClient.verifyOtp(
        baseUrl: baseUrl,
        email: email,
        code: code,
      );

      if (!mounted) {
        return;
      }

      widget.onLoggedIn(session);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _error = error.message;
        _notice = null;
        _debugCode = error.debugCode ?? _debugCode;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      _showError(
        'Unable to reach the backend. Check the URL and ensure Laravel is running.',
      );
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _resendOtp() async {
    FocusManager.instance.primaryFocus?.unfocus();

    final baseUrl = _baseUrlController.text.trim();
    final email = _knownEmail;

    if (baseUrl.isEmpty || email.isEmpty) {
      _showError(
        'Backend URL and email are required before resending the OTP.',
      );
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });

    try {
      final result = await widget.apiClient.resendOtp(
        baseUrl: baseUrl,
        email: email,
      );

      if (!mounted) {
        return;
      }

      _prepareVerification(
        email: result.email,
        message: result.message,
        debugCode: result.debugCode,
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      _showError(error.message);
    } catch (_) {
      if (!mounted) {
        return;
      }

      _showError(
        'Unable to reach the backend. Check the URL and ensure Laravel is running.',
      );
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  void _switchStage(_AuthStage stage) {
    setState(() {
      _stage = stage;
      _error = null;
      _notice = null;

      if (stage != _AuthStage.verify) {
        _debugCode = null;
      }
    });
  }

  void _openManualVerification() {
    _prepareVerification(
      email: _knownEmail,
      message: 'Enter the OTP from your inbox to finish verification.',
    );
  }

  void _prepareVerification({
    required String email,
    required String message,
    String? debugCode,
  }) {
    setState(() {
      _otpRequested = true;
      _stage = _AuthStage.verify;
      _otpEmailController.text = email;
      _otpCodeController.clear();
      _error = null;
      _notice = message;
      _debugCode = debugCode;
    });
  }

  String get _knownEmail {
    final candidates = <String>[
      _otpEmailController.text.trim(),
      _signupEmailController.text.trim(),
      _emailController.text.trim(),
    ];

    for (final candidate in candidates) {
      if (candidate.isNotEmpty) {
        return candidate;
      }
    }

    return '';
  }

  void _showError(String message) {
    setState(() {
      _error = message;
      _notice = null;
    });
  }
}

class _StagePill extends StatelessWidget {
  const _StagePill({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          gradient: selected ? ReaderPalette.editorialGradient : null,
          color: selected ? null : ReaderPalette.surfaceMuted,
          border: Border.all(
            color: selected ? Colors.transparent : ReaderPalette.border,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: selected ? ReaderPalette.inverseText : ReaderPalette.ink,
            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
          ),
        ),
      ),
    );
  }
}

class _HeroChip extends StatelessWidget {
  const _HeroChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: const Color(0x22FFFFFF),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: ReaderPalette.inverseText),
          const SizedBox(width: 8),
          Text(
            label,
            style: const TextStyle(
              fontWeight: FontWeight.w600,
              color: ReaderPalette.inverseText,
            ),
          ),
        ],
      ),
    );
  }
}
