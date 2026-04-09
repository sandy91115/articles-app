import 'dart:convert';
import 'dart:io';

import 'package:file_selector/file_selector.dart';
import 'package:http/http.dart' as http;

import 'models.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.payload});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? payload;

  String? get debugCode => asNullableString(payload?['debug_code']);

  @override
  String toString() => message;
}

class OtpDeliveryResult {
  const OtpDeliveryResult({
    required this.email,
    required this.message,
    required this.debugCode,
  });

  final String email;
  final String message;
  final String? debugCode;
}

class ReaderApiClient {
  ReaderApiClient({HttpClient? httpClient})
    : _httpClient = httpClient ?? HttpClient();

  final HttpClient _httpClient;

  Future<OtpDeliveryResult> register({
    required String baseUrl,
    required String name,
    required String email,
    required String phone,
    required String username,
    required String password,
    required String confirmPassword,
  }) async {
    final response = await _request(
      baseUrl: baseUrl,
      path: '/api/auth/register',
      method: 'POST',
      body: <String, Object?>{
        'name': name,
        'email': email,
        'phone': phone,
        'user': username,
        'username': username,
        'password': password,
        'password_confirmation': confirmPassword,
        'role': 'reader',
      },
    );

    return OtpDeliveryResult(
      email: email,
      message: asString(
        response['message'],
        fallback: 'Account created. Verify the OTP to continue.',
      ),
      debugCode: asNullableString(response['debug_code']),
    );
  }

  Future<ReaderSession> login({
    required String baseUrl,
    required String email,
    required String password,
  }) async {
    final response = await _request(
      baseUrl: baseUrl,
      path: '/api/auth/login',
      method: 'POST',
      body: <String, Object?>{
        'email': email,
        'password': password,
        'device_name': 'reader-mobile-app',
      },
    );

    return ReaderSession(
      token: asString(response['token']),
      baseUrl: baseUrl,
      user: ReaderUser.fromJson(response['user'] as Map<String, dynamic>),
    );
  }

  Future<ReaderSession> verifyOtp({
    required String baseUrl,
    required String email,
    required String code,
  }) async {
    final response = await _request(
      baseUrl: baseUrl,
      path: '/api/auth/verify-otp',
      method: 'POST',
      body: <String, Object?>{
        'email': email,
        'code': code,
        'device_name': 'reader-mobile-app-verification',
      },
    );

    return ReaderSession(
      token: asString(response['token']),
      baseUrl: baseUrl,
      user: ReaderUser.fromJson(response['user'] as Map<String, dynamic>),
    );
  }

  Future<OtpDeliveryResult> resendOtp({
    required String baseUrl,
    required String email,
  }) async {
    final response = await _request(
      baseUrl: baseUrl,
      path: '/api/auth/resend-otp',
      method: 'POST',
      body: <String, Object?>{'email': email},
    );

    return OtpDeliveryResult(
      email: email,
      message: asString(
        response['message'],
        fallback: 'A new verification code has been issued.',
      ),
      debugCode: asNullableString(response['debug_code']),
    );
  }

  Future<ReaderUser> me(ReaderSession session) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/auth/me',
      token: session.token,
    );

    return ReaderUser.fromJson(response['user'] as Map<String, dynamic>);
  }

  Future<void> logout(ReaderSession session) async {
    await _request(
      baseUrl: session.baseUrl,
      path: '/api/auth/logout',
      method: 'POST',
      token: session.token,
    );
  }

  Future<ReaderUser> updateProfilePhoto(
    ReaderSession session,
    XFile photo,
  ) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${_normalizedBaseUrl(session.baseUrl)}/api/auth/profile-photo'),
    );
    request.headers[HttpHeaders.acceptHeader] = 'application/json';
    request.headers[HttpHeaders.authorizationHeader] = 'Bearer ${session.token}';
    request.files.add(
      http.MultipartFile(
        'photo',
        photo.openRead(),
        await photo.length(),
        filename: photo.name,
      ),
    );

    final streamed = await request.send();
    final response = await http.Response.fromStream(streamed);
    final decodedMap = _decodePayload(response.body);

    if (response.statusCode >= 400) {
      throw ApiException(
        _extractMessage(decodedMap) ??
            'Request failed with status ${response.statusCode}',
        statusCode: response.statusCode,
        payload: decodedMap,
      );
    }

    if (decodedMap == null || decodedMap['user'] is! Map<String, dynamic>) {
      throw ApiException('The server returned an invalid profile response.');
    }

    return ReaderUser.fromJson(decodedMap['user'] as Map<String, dynamic>);
  }

  Future<void> changePassword(
    ReaderSession session, {
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    await _request(
      baseUrl: session.baseUrl,
      path: '/api/auth/password',
      method: 'PUT',
      token: session.token,
      body: <String, Object?>{
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': confirmPassword,
      },
    );
  }

  Future<WalletSummary> fetchWallet(ReaderSession session) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/wallet',
      token: session.token,
    );

    return WalletSummary.fromJson(response);
  }

  Future<List<ArticleSummary>> fetchArticles(ReaderSession session) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/articles',
      token: session.token,
    );

    final articles = response['articles'] as List<dynamic>? ?? <dynamic>[];

    return articles
        .cast<Map<String, dynamic>>()
        .map(ArticleSummary.fromJson)
        .toList(growable: false);
  }

  Future<List<WalletTransaction>> fetchTransactions(
    ReaderSession session,
  ) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/wallet/transactions',
      token: session.token,
    );

    final transactions =
        response['transactions'] as List<dynamic>? ?? <dynamic>[];

    return transactions
        .cast<Map<String, dynamic>>()
        .map(WalletTransaction.fromJson)
        .toList(growable: false);
  }

  Future<List<UnlockRecord>> fetchUnlocks(ReaderSession session) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/unlocks',
      token: session.token,
    );

    final unlocks = response['unlocks'] as List<dynamic>? ?? <dynamic>[];

    return unlocks
        .cast<Map<String, dynamic>>()
        .map(UnlockRecord.fromJson)
        .toList(growable: false);
  }

  Future<ArticleDetail> fetchArticleDetail(
    ReaderSession session,
    String slug,
  ) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/articles/$slug',
      token: session.token,
    );

    return ArticleDetail.fromJson(response['article'] as Map<String, dynamic>);
  }

  Future<void> unlockArticle(ReaderSession session, String slug) async {
    await _request(
      baseUrl: session.baseUrl,
      path: '/api/articles/$slug/unlock',
      method: 'POST',
      token: session.token,
    );
  }

  Future<PaymentOrderPreview> createPurchaseOrder(
    ReaderSession session,
    int credits,
  ) async {
    final response = await _request(
      baseUrl: session.baseUrl,
      path: '/api/wallet/purchase-orders',
      method: 'POST',
      token: session.token,
      body: <String, Object?>{'credits': credits},
    );

    return PaymentOrderPreview.fromJson(
      response['order'] as Map<String, dynamic>,
    );
  }

  Future<DashboardBundle> loadDashboard(ReaderSession session) async {
    final results = await Future.wait<Object>(<Future<Object>>[
      me(session),
      fetchWallet(session),
      fetchArticles(session),
      fetchTransactions(session),
      fetchUnlocks(session),
    ]);

    return DashboardBundle(
      user: results[0] as ReaderUser,
      wallet: results[1] as WalletSummary,
      articles: results[2] as List<ArticleSummary>,
      transactions: results[3] as List<WalletTransaction>,
      unlocks: results[4] as List<UnlockRecord>,
      loadedAt: DateTime.now(),
    );
  }

  Future<Map<String, dynamic>> _request({
    required String baseUrl,
    required String path,
    String method = 'GET',
    String? token,
    Map<String, Object?>? body,
  }) async {
    final uri = Uri.parse('${_normalizedBaseUrl(baseUrl)}$path');

    final request = await _httpClient.openUrl(method, uri);
    request.headers.set(HttpHeaders.acceptHeader, 'application/json');

    if (token != null && token.isNotEmpty) {
      request.headers.set(HttpHeaders.authorizationHeader, 'Bearer $token');
    }

    if (body != null) {
      request.headers.contentType = ContentType.json;
      request.write(jsonEncode(body));
    }

    final response = await request.close();
    final payload = await response.transform(utf8.decoder).join();
    final decodedMap = _decodePayload(payload);

    if (response.statusCode >= 400) {
      throw ApiException(
        _extractMessage(decodedMap) ??
            'Request failed with status ${response.statusCode}',
        statusCode: response.statusCode,
        payload: decodedMap,
      );
    }

    if (decodedMap != null) {
      return decodedMap;
    }

    return <String, dynamic>{};
  }

  String _normalizedBaseUrl(String baseUrl) {
    return baseUrl.endsWith('/')
        ? baseUrl.substring(0, baseUrl.length - 1)
        : baseUrl;
  }

  Map<String, dynamic>? _decodePayload(String payload) {
    final decoded = payload.isEmpty ? null : jsonDecode(payload);

    return decoded is Map ? Map<String, dynamic>.from(decoded) : null;
  }

  String? _extractMessage(dynamic decoded) {
    if (decoded is Map<String, dynamic>) {
      final directMessage = decoded['message'];

      if (directMessage is String && directMessage.trim().isNotEmpty) {
        return _normalizeMessage(directMessage);
      }

      final errors = decoded['errors'];

      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final firstEntry = errors.entries.first.value;

        if (firstEntry is List && firstEntry.isNotEmpty) {
          return firstEntry.first.toString();
        }
      }
    }

    return null;
  }

  String _normalizeMessage(String message) {
    const infrastructureIndicators = <String>[
      'SQLSTATE[',
      'Connection refused',
      'actively refused',
      'could not find driver',
      'unable to open database file',
      'database is locked',
    ];

    for (final indicator in infrastructureIndicators) {
      if (message.contains(indicator)) {
        return 'The backend database is unavailable. Start MySQL, check backend/.env, and run the Laravel migrations before trying again.';
      }
    }

    return message;
  }
}
