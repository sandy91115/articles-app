import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:reader_app/src/app.dart';
import 'package:reader_app/src/models.dart';
import 'package:reader_app/src/screens/shell_screen.dart';

void main() {
  testWidgets('shows reader login screen on launch', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const ReaderApp());

    expect(find.text('Reader login'), findsOneWidget);
    expect(find.text('Open Reader App'), findsOneWidget);
    expect(find.text('Daily Reading'), findsOneWidget);
  });

  testWidgets('builds the populated reader home screen', (
    WidgetTester tester,
  ) async {
    final bundle = DashboardBundle(
      user: ReaderUser(
        id: 1,
        name: 'Demo Reader',
        username: 'demo-reader',
        profilePhotoUrl: null,
        email: 'reader@example.com',
        phone: '9876543210',
        role: 'reader',
        walletBalance: 320,
        createdAt: DateTime(2026, 4, 8, 9, 0),
      ),
      wallet: const WalletSummary(
        walletBalance: 320,
        creditsPerRupee: 1,
        minPurchaseCredits: 50,
      ),
      articles: const [
        ArticleSummary(
          id: 1,
          category: 'Technology',
          title: 'AI Companies Are Racing To Build Indian-Language Assistants',
          slug: 'ai-companies',
          imageUrl: 'https://example.com/ai.jpg',
          previewText:
              'Voice, translation, and search products are shifting fast.',
          price: 65,
          accessDurationHours: 48,
          viewCount: 3620,
          unlockCount: 533,
          ratingAverage: 4.9,
          ratingCount: 214,
          authorName: 'Priya Nair',
          isUnlocked: false,
        ),
        ArticleSummary(
          id: 2,
          category: 'Business',
          title: 'Startup Funding Signals A Cautious Comeback',
          slug: 'startup-funding',
          imageUrl: 'https://example.com/business.jpg',
          previewText:
              'Founders with efficient growth are reopening investor conversations.',
          price: 60,
          accessDurationHours: 48,
          viewCount: 2550,
          unlockCount: 366,
          ratingAverage: 4.7,
          ratingCount: 128,
          authorName: 'Demo Author',
          isUnlocked: true,
        ),
      ],
      transactions: const [],
      unlocks: const [],
      loadedAt: DateTime(2026, 4, 8, 18, 30),
    );

    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData.light(useMaterial3: true),
        home: Scaffold(
          body: ReaderHomeTab(
            bundle: bundle,
            onRefresh: () async {},
            onOpenArticle: (_) {},
            onShareArticle: (_) {},
          ),
        ),
      ),
    );
    await tester.pump();
    expect(tester.takeException(), isNull);

    expect(find.text('Articles'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Top Articles'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Top Articles'), findsOneWidget);
  });
}
