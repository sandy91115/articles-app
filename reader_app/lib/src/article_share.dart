import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import 'models.dart';
import 'reader_palette.dart';

class ArticleShareData {
  const ArticleShareData({
    required this.baseUrl,
    required this.title,
    required this.slug,
    required this.authorName,
    required this.category,
    required this.previewText,
  });

  factory ArticleShareData.fromSummary({
    required String baseUrl,
    required ArticleSummary article,
  }) {
    return ArticleShareData(
      baseUrl: baseUrl,
      title: article.title,
      slug: article.slug,
      authorName: article.authorName,
      category: article.category,
      previewText: article.previewText,
    );
  }

  factory ArticleShareData.fromDetail({
    required String baseUrl,
    required ArticleDetail article,
  }) {
    return ArticleShareData(
      baseUrl: baseUrl,
      title: article.title,
      slug: article.slug,
      authorName: article.authorName,
      category: article.category,
      previewText: article.previewText,
    );
  }

  final String baseUrl;
  final String title;
  final String slug;
  final String authorName;
  final String category;
  final String previewText;

  String get previewUrl => buildArticlePreviewUrl(baseUrl, slug);

  String get shortPreview {
    final compact = previewText.replaceAll(RegExp(r'\s+'), ' ').trim();
    if (compact.length <= 160) {
      return compact;
    }

    return '${compact.substring(0, 157)}...';
  }

  String get shareText =>
      '$title by $authorName\n\n$shortPreview\n\nRead the preview: $previewUrl';
}

String buildArticlePreviewUrl(String baseUrl, String slug) {
  final normalizedBase = baseUrl.endsWith('/')
      ? baseUrl.substring(0, baseUrl.length - 1)
      : baseUrl;

  return '$normalizedBase/stories/$slug';
}

Future<void> showArticleShareSheet(
  BuildContext context, {
  required ArticleShareData article,
}) async {
  final messenger = ScaffoldMessenger.of(context);
  final previewUrl = article.previewUrl;
  final shareText = article.shareText;
  final shareUrlEncoded = Uri.encodeComponent(previewUrl);
  final shareTextEncoded = Uri.encodeComponent(shareText);
  final summaryEncoded = Uri.encodeComponent('${article.title} by ${article.authorName}');

  Future<void> launchFromSheet(
    BuildContext sheetContext,
    Uri uri, {
    required String failureMessage,
  }) async {
    Navigator.of(sheetContext).pop();

    final launched = await launchUrl(uri, mode: LaunchMode.platformDefault);

    if (!launched && context.mounted) {
      messenger.showSnackBar(SnackBar(content: Text(failureMessage)));
    }
  }

  Future<void> copyLink(BuildContext sheetContext) async {
    Navigator.of(sheetContext).pop();
    await Clipboard.setData(ClipboardData(text: previewUrl));

    if (context.mounted) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Story preview link copied to clipboard.')),
      );
    }
  }

  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: ReaderPalette.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (sheetContext) {
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Share article',
                style: Theme.of(
                  context,
                ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 8),
              const Text(
                'Pick a social destination or copy the preview link.',
                style: TextStyle(color: ReaderPalette.inkMuted, height: 1.5),
              ),
              const SizedBox(height: 18),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  gradient: ReaderPalette.surfaceGradient,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: ReaderPalette.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _ShareMetaChip(label: article.category),
                        _ShareMetaChip(label: article.authorName),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      article.title,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        height: 1.25,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      article.shortPreview,
                      style: const TextStyle(
                        color: ReaderPalette.inkMuted,
                        height: 1.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  _ShareActionTile(
                    icon: Icons.forum_rounded,
                    label: 'WhatsApp',
                    accent: const Color(0xFF25D366),
                    onTap: () => launchFromSheet(
                      sheetContext,
                      Uri.parse('https://wa.me/?text=$shareTextEncoded'),
                      failureMessage: 'Unable to open WhatsApp sharing here.',
                    ),
                  ),
                  _ShareActionTile(
                    icon: Icons.alternate_email_rounded,
                    label: 'X',
                    accent: const Color(0xFF111827),
                    onTap: () => launchFromSheet(
                      sheetContext,
                      Uri.parse(
                        'https://twitter.com/intent/tweet?text=$shareTextEncoded',
                      ),
                      failureMessage: 'Unable to open X sharing here.',
                    ),
                  ),
                  _ShareActionTile(
                    icon: Icons.send_rounded,
                    label: 'Telegram',
                    accent: const Color(0xFF2AABEE),
                    onTap: () => launchFromSheet(
                      sheetContext,
                      Uri.parse(
                        'https://t.me/share/url?url=$shareUrlEncoded&text=$summaryEncoded',
                      ),
                      failureMessage: 'Unable to open Telegram sharing here.',
                    ),
                  ),
                  _ShareActionTile(
                    icon: Icons.thumb_up_alt_rounded,
                    label: 'Facebook',
                    accent: const Color(0xFF1877F2),
                    onTap: () => launchFromSheet(
                      sheetContext,
                      Uri.parse(
                        'https://www.facebook.com/sharer/sharer.php?u=$shareUrlEncoded',
                      ),
                      failureMessage: 'Unable to open Facebook sharing here.',
                    ),
                  ),
                  _ShareActionTile(
                    icon: Icons.language_rounded,
                    label: 'Preview',
                    accent: ReaderPalette.primarySoft,
                    onTap: () => launchFromSheet(
                      sheetContext,
                      Uri.parse(previewUrl),
                      failureMessage: 'Unable to open the story preview.',
                    ),
                  ),
                  _ShareActionTile(
                    icon: Icons.copy_rounded,
                    label: 'Copy link',
                    accent: ReaderPalette.primary,
                    onTap: () => copyLink(sheetContext),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    },
  );
}

class _ShareActionTile extends StatelessWidget {
  const _ShareActionTile({
    required this.icon,
    required this.label,
    required this.accent,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color accent;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(20),
      child: Ink(
        width: 96,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        decoration: BoxDecoration(
          color: ReaderPalette.surfaceMuted,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: ReaderPalette.border),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.18),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: accent),
            ),
            const SizedBox(height: 10),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: ReaderPalette.ink,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ShareMetaChip extends StatelessWidget {
  const _ShareMetaChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: ReaderPalette.surfaceMuted,
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: ReaderPalette.primary,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}
