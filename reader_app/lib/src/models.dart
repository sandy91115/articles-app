class ReaderSession {
  const ReaderSession({
    required this.token,
    required this.baseUrl,
    required this.user,
  });

  final String token;
  final String baseUrl;
  final ReaderUser user;
}

class ReaderUser {
  const ReaderUser({
    required this.id,
    required this.name,
    required this.username,
    required this.profilePhotoUrl,
    required this.email,
    required this.phone,
    required this.role,
    required this.walletBalance,
    required this.createdAt,
  });

  factory ReaderUser.fromJson(Map<String, dynamic> json) {
    return ReaderUser(
      id: asInt(json['id']),
      name: asString(json['name'], fallback: 'Reader'),
      username: asNullableString(json['username'] ?? json['user']),
      profilePhotoUrl: asNullableString(json['profile_photo_url']),
      email: asString(json['email'], fallback: ''),
      phone: asNullableString(json['phone']),
      role: asString(json['role'], fallback: 'reader'),
      walletBalance: asInt(json['wallet_balance']),
      createdAt: tryParseDateTime(json['created_at']),
    );
  }

  final int id;
  final String name;
  final String? username;
  final String? profilePhotoUrl;
  final String email;
  final String? phone;
  final String role;
  final int walletBalance;
  final DateTime? createdAt;

  String get initials {
    final parts = name
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .toList();

    if (parts.isEmpty) {
      return 'RD';
    }

    if (parts.length == 1) {
      final single = parts.first;
      return single.substring(0, single.length > 1 ? 2 : 1).toUpperCase();
    }

    return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
  }
}

class WalletSummary {
  const WalletSummary({
    required this.walletBalance,
    required this.creditsPerRupee,
    required this.minPurchaseCredits,
  });

  factory WalletSummary.fromJson(Map<String, dynamic> json) {
    return WalletSummary(
      walletBalance: asInt(json['wallet_balance']),
      creditsPerRupee: asInt(json['credits_per_rupee'], fallback: 1),
      minPurchaseCredits: asInt(json['min_purchase_credits'], fallback: 50),
    );
  }

  final int walletBalance;
  final int creditsPerRupee;
  final int minPurchaseCredits;
}

class ArticleSummary {
  const ArticleSummary({
    required this.id,
    required this.category,
    required this.title,
    required this.slug,
    required this.imageUrl,
    required this.previewText,
    required this.price,
    required this.accessDurationHours,
    required this.viewCount,
    required this.unlockCount,
    required this.ratingAverage,
    required this.ratingCount,
    required this.authorName,
    required this.isUnlocked,
  });

  factory ArticleSummary.fromJson(Map<String, dynamic> json) {
    final author =
        json['author'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return ArticleSummary(
      id: asInt(json['id']),
      category: asString(json['category'], fallback: 'General'),
      title: asString(json['title'], fallback: 'Untitled'),
      slug: asString(json['slug'], fallback: ''),
      imageUrl: asNullableString(json['image_url']),
      previewText: asString(json['preview_text'], fallback: ''),
      price: asInt(json['price']),
      accessDurationHours: asNullableInt(json['access_duration_hours']),
      viewCount: asInt(json['view_count']),
      unlockCount: asInt(json['unlock_count']),
      ratingAverage: asDouble(json['rating_average']),
      ratingCount: asInt(json['rating_count']),
      authorName: asString(author['name'], fallback: 'Unknown Author'),
      isUnlocked: json['is_unlocked'] == true,
    );
  }

  final int id;
  final String category;
  final String title;
  final String slug;
  final String? imageUrl;
  final String previewText;
  final int price;
  final int? accessDurationHours;
  final int viewCount;
  final int unlockCount;
  final double ratingAverage;
  final int ratingCount;
  final String authorName;
  final bool isUnlocked;
}

class ArticleDetail {
  const ArticleDetail({
    required this.id,
    required this.category,
    required this.title,
    required this.slug,
    required this.imageUrl,
    required this.previewText,
    required this.content,
    required this.price,
    required this.status,
    required this.authorName,
    required this.isUnlocked,
    required this.viewCount,
    required this.unlockCount,
    required this.ratingAverage,
    required this.ratingCount,
    required this.accessExpiresAt,
    required this.accessDurationHours,
  });

  factory ArticleDetail.fromJson(Map<String, dynamic> json) {
    final author =
        json['author'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return ArticleDetail(
      id: asInt(json['id']),
      category: asString(json['category'], fallback: 'General'),
      title: asString(json['title'], fallback: 'Untitled'),
      slug: asString(json['slug'], fallback: ''),
      imageUrl: asNullableString(json['image_url']),
      previewText: asString(json['preview_text'], fallback: ''),
      content: asNullableString(json['content']),
      price: asInt(json['price']),
      status: asString(json['status'], fallback: 'published'),
      authorName: asString(author['name'], fallback: 'Unknown Author'),
      isUnlocked: json['is_unlocked'] == true,
      viewCount: asInt(json['view_count']),
      unlockCount: asInt(json['unlock_count']),
      ratingAverage: asDouble(json['rating_average']),
      ratingCount: asInt(json['rating_count']),
      accessExpiresAt: tryParseDateTime(json['access_expires_at']),
      accessDurationHours: asNullableInt(json['access_duration_hours']),
    );
  }

  final int id;
  final String category;
  final String title;
  final String slug;
  final String? imageUrl;
  final String previewText;
  final String? content;
  final int price;
  final String status;
  final String authorName;
  final bool isUnlocked;
  final int viewCount;
  final int unlockCount;
  final double ratingAverage;
  final int ratingCount;
  final DateTime? accessExpiresAt;
  final int? accessDurationHours;
}

class WalletTransaction {
  const WalletTransaction({
    required this.id,
    required this.type,
    required this.amount,
    required this.source,
    required this.status,
    required this.createdAt,
  });

  factory WalletTransaction.fromJson(Map<String, dynamic> json) {
    return WalletTransaction(
      id: asInt(json['id']),
      type: asString(json['type'], fallback: 'debit'),
      amount: asInt(json['amount']),
      source: asString(json['source'], fallback: 'wallet'),
      status: asString(json['status'], fallback: 'completed'),
      createdAt: tryParseDateTime(json['created_at']),
    );
  }

  final int id;
  final String type;
  final int amount;
  final String source;
  final String status;
  final DateTime? createdAt;

  bool get isCredit => type == 'credit';
}

class UnlockRecord {
  const UnlockRecord({
    required this.id,
    required this.creditsSpent,
    required this.unlockedAt,
    required this.expiresAt,
    required this.isActive,
    required this.articleTitle,
    required this.articleSlug,
    required this.articleImageUrl,
    required this.authorName,
  });

  factory UnlockRecord.fromJson(Map<String, dynamic> json) {
    final article =
        json['article'] as Map<String, dynamic>? ?? <String, dynamic>{};
    final author =
        article['author'] as Map<String, dynamic>? ?? <String, dynamic>{};

    return UnlockRecord(
      id: asInt(json['id']),
      creditsSpent: asInt(json['credits_spent']),
      unlockedAt: tryParseDateTime(json['unlocked_at']),
      expiresAt: tryParseDateTime(json['expires_at']),
      isActive: json['is_active'] == true,
      articleTitle: asString(article['title'], fallback: 'Premium Article'),
      articleSlug: asString(article['slug'], fallback: ''),
      articleImageUrl: asNullableString(article['image_url']),
      authorName: asString(author['name'], fallback: 'Unknown Author'),
    );
  }

  final int id;
  final int creditsSpent;
  final DateTime? unlockedAt;
  final DateTime? expiresAt;
  final bool isActive;
  final String articleTitle;
  final String articleSlug;
  final String? articleImageUrl;
  final String authorName;
}

class PaymentOrderPreview {
  const PaymentOrderPreview({
    required this.reference,
    required this.creditAmount,
    required this.providerOrderId,
  });

  factory PaymentOrderPreview.fromJson(Map<String, dynamic> json) {
    return PaymentOrderPreview(
      reference: asString(json['reference'], fallback: ''),
      creditAmount: asInt(json['credit_amount']),
      providerOrderId: asNullableString(json['provider_order_id']),
    );
  }

  final String reference;
  final int creditAmount;
  final String? providerOrderId;
}

class DashboardBundle {
  const DashboardBundle({
    required this.user,
    required this.wallet,
    required this.articles,
    required this.transactions,
    required this.unlocks,
    required this.loadedAt,
  });

  final ReaderUser user;
  final WalletSummary wallet;
  final List<ArticleSummary> articles;
  final List<WalletTransaction> transactions;
  final List<UnlockRecord> unlocks;
  final DateTime loadedAt;
}

int asInt(Object? value, {int fallback = 0}) {
  if (value is int) {
    return value;
  }

  if (value is double) {
    return value.toInt();
  }

  if (value is String) {
    return int.tryParse(value) ?? fallback;
  }

  return fallback;
}

int? asNullableInt(Object? value) {
  if (value == null) {
    return null;
  }

  return asInt(value);
}

double asDouble(Object? value, {double fallback = 0}) {
  if (value is double) {
    return value;
  }

  if (value is int) {
    return value.toDouble();
  }

  if (value is String) {
    return double.tryParse(value) ?? fallback;
  }

  return fallback;
}

String asString(Object? value, {String fallback = ''}) {
  if (value == null) {
    return fallback;
  }

  return value.toString();
}

String? asNullableString(Object? value) {
  if (value == null) {
    return null;
  }

  final text = value.toString().trim();

  return text.isEmpty ? null : text;
}

DateTime? tryParseDateTime(Object? value) {
  if (value == null) {
    return null;
  }

  return DateTime.tryParse(value.toString());
}

String? resolveReaderAssetUrl(String baseUrl, String? pathOrUrl) {
  final value = asNullableString(pathOrUrl);
  if (value == null) {
    return null;
  }

  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value;
  }

  final normalizedBase = baseUrl.endsWith('/')
      ? baseUrl.substring(0, baseUrl.length - 1)
      : baseUrl;
  final normalizedPath = value.startsWith('/') ? value : '/$value';

  return '$normalizedBase$normalizedPath';
}
