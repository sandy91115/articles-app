import 'package:flutter/material.dart';

import '../api_client.dart';
import '../article_share.dart';
import '../models.dart';
import '../reader_nav_bar.dart';
import '../reader_palette.dart';

class ArticleDetailScreen extends StatefulWidget {
  const ArticleDetailScreen({
    super.key,
    required this.apiClient,
    required this.session,
    required this.article,
    required this.currentTabIndex,
    required this.onNavigateToTab,
  });

  final ReaderApiClient apiClient;
  final ReaderSession session;
  final ArticleSummary article;
  final int currentTabIndex;
  final ValueChanged<int> onNavigateToTab;

  @override
  State<ArticleDetailScreen> createState() => _ArticleDetailScreenState();
}

class _ArticleDetailScreenState extends State<ArticleDetailScreen> {
  late Future<ArticleDetail> _detailFuture;
  bool _unlocking = false;
  bool _didUnlock = false;

  @override
  void initState() {
    super.initState();
    _detailFuture = _load();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<bool>(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) {
          return;
        }

        Navigator.of(context).pop(_didUnlock);
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Article'),
          backgroundColor: Colors.transparent,
          leading: IconButton(
            onPressed: () => Navigator.of(context).pop(_didUnlock),
            icon: const Icon(Icons.arrow_back_ios_new_rounded),
          ),
          actions: [
            IconButton(
              onPressed: _shareArticle,
              tooltip: 'Share article',
              icon: const Icon(Icons.share_outlined),
            ),
          ],
        ),
        bottomNavigationBar: ReaderBottomBar(
          currentIndex: widget.currentTabIndex,
          onChanged: _navigateToTab,
        ),
        body: FutureBuilder<ArticleDetail>(
          future: _detailFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }

            if (snapshot.hasError) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text(
                    snapshot.error.toString(),
                    textAlign: TextAlign.center,
                  ),
                ),
              );
            }

            final detail = snapshot.data!;

            return ListView(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
              children: [
                if (detail.imageUrl != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(24),
                    child: AspectRatio(
                      aspectRatio: 16 / 10,
                      child: Image.network(
                        detail.imageUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            _imageFallback(),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                ] else ...[
                  _imageFallback(),
                  const SizedBox(height: 20),
                ],
                Text(
                  detail.title,
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    _metaChip(Icons.person_outline, detail.authorName),
                    _metaChip(Icons.sell_outlined, detail.category),
                    _metaChip(
                      Icons.star_outline_rounded,
                      '${detail.ratingAverage.toStringAsFixed(1)} • ${detail.ratingCount} ratings',
                    ),
                    _metaChip(
                      Icons.monetization_on_outlined,
                      _formatCoins(detail.price),
                    ),
                    _metaChip(
                      Icons.lock_clock_outlined,
                      detail.accessDurationHours == null
                          ? 'Lifetime access'
                          : '${detail.accessDurationHours} hrs',
                    ),
                    if (detail.isUnlocked)
                      _metaChip(Icons.verified_outlined, 'Unlocked'),
                  ],
                ),
                const SizedBox(height: 20),
                _sectionCard(
                  title: 'Preview',
                  child: Text(
                    detail.previewText,
                    style: const TextStyle(
                      color: ReaderPalette.inkMuted,
                      height: 1.65,
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                if (detail.content != null)
                  _sectionCard(
                    title: 'Full Story',
                    child: Text(
                      detail.content!,
                      style: const TextStyle(
                        color: ReaderPalette.ink,
                        height: 1.78,
                      ),
                    ),
                  )
                else
                  _sectionCard(
                    title: 'Premium Access',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'The full article is currently locked. Coins will be deducted from the backend wallet when you unlock it.',
                          style: TextStyle(
                            color: ReaderPalette.inkMuted,
                            height: 1.65,
                          ),
                        ),
                        const SizedBox(height: 16),
                        SizedBox(
                          width: double.infinity,
                          child: ElevatedButton(
                            onPressed: _unlocking
                                ? null
                                : () => _confirmUnlock(detail),
                            child: Text(
                              _unlocking
                                  ? 'Unlocking...'
                                  : 'Unlock for ${_formatCoins(detail.price)}',
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 18),
                _sectionCard(
                  title: 'Stats',
                  child: Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      _smallStat('Views', '${detail.viewCount}'),
                      _smallStat('Unlocks', '${detail.unlockCount}'),
                      _smallStat(
                        'Rating',
                        '${detail.ratingAverage.toStringAsFixed(1)} / 5',
                      ),
                      _smallStat('Ratings', '${detail.ratingCount}'),
                      _smallStat(
                        'Access',
                        detail.accessExpiresAt == null
                            ? 'Active'
                            : _formatDate(detail.accessExpiresAt!),
                      ),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Future<ArticleDetail> _load() {
    return widget.apiClient.fetchArticleDetail(
      widget.session,
      widget.article.slug,
    );
  }

  Future<void> _confirmUnlock(ArticleDetail detail) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return Dialog(
          backgroundColor: Colors.transparent,
          child: Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: ReaderPalette.surface,
              borderRadius: BorderRadius.circular(28),
              border: Border.all(color: ReaderPalette.border),
              boxShadow: ReaderPalette.softShadow,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    gradient: ReaderPalette.navGradient,
                  ),
                  child: const Icon(
                    Icons.lock_open_rounded,
                    color: ReaderPalette.inverseText,
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  'Buy this article?',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  'Buy "${detail.title}" with ${_formatCoins(detail.price)}.',
                  style: const TextStyle(
                    color: ReaderPalette.inkMuted,
                    height: 1.55,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  detail.accessDurationHours == null
                      ? 'Your wallet will be charged once and access will stay active.'
                      : 'Your wallet will be charged once and access will stay active for ${detail.accessDurationHours} hours.',
                  style: const TextStyle(
                    color: ReaderPalette.inkMuted,
                    height: 1.55,
                  ),
                ),
                const SizedBox(height: 18),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: ReaderPalette.surfaceMuted,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: ReaderPalette.border),
                  ),
                  child: Row(
                    children: [
                      const Icon(
                        Icons.monetization_on_outlined,
                        color: ReaderPalette.primary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Buy this article with ${_formatCoins(detail.price)}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 22),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => Navigator.of(dialogContext).pop(false),
                        child: const Text('Cancel'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () => Navigator.of(dialogContext).pop(true),
                        child: const Text('Buy Article'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );

    if (confirmed == true) {
      await _unlock();
    }
  }

  Future<void> _unlock() async {
    setState(() {
      _unlocking = true;
    });

    try {
      await widget.apiClient.unlockArticle(widget.session, widget.article.slug);
      _didUnlock = true;
      setState(() {
        _detailFuture = _load();
      });
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Article unlocked successfully.')),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() {
          _unlocking = false;
        });
      }
    }
  }

  Future<void> _shareArticle([ArticleDetail? detail]) {
    final payload = detail == null
        ? ArticleShareData.fromSummary(
            baseUrl: widget.session.baseUrl,
            article: widget.article,
          )
        : ArticleShareData.fromDetail(
            baseUrl: widget.session.baseUrl,
            article: detail,
          );

    return showArticleShareSheet(context, article: payload);
  }

  void _navigateToTab(int index) {
    widget.onNavigateToTab(index);

    if (mounted) {
      Navigator.of(context).pop(_didUnlock);
    }
  }

  Widget _imageFallback() {
    return Container(
      height: 220,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [
            ReaderPalette.primary,
            ReaderPalette.primarySoft,
            ReaderPalette.secondary,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      alignment: Alignment.center,
      child: const Icon(
        Icons.menu_book_rounded,
        size: 40,
        color: ReaderPalette.inverseText,
      ),
    );
  }

  Widget _metaChip(IconData icon, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: ReaderPalette.surfaceMuted,
        border: Border.all(color: ReaderPalette.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: ReaderPalette.primary),
          const SizedBox(width: 8),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _sectionCard({required String title, required Widget child}) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w700,
                color: ReaderPalette.primary,
              ),
            ),
            const SizedBox(height: 14),
            child,
          ],
        ),
      ),
    );
  }

  Widget _smallStat(String label, String value) {
    return Container(
      width: 100,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: ReaderPalette.surfaceMuted,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: ReaderPalette.inkMuted,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 6),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }

  String _formatDate(DateTime value) {
    const months = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    final hour = value.hour == 0
        ? 12
        : (value.hour > 12 ? value.hour - 12 : value.hour);
    final minute = value.minute.toString().padLeft(2, '0');
    final suffix = value.hour >= 12 ? 'PM' : 'AM';

    return '${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]}, ${hour.toString().padLeft(2, '0')}:$minute $suffix';
  }

  String _formatCoins(int value) {
    return '$value coins';
  }
}
