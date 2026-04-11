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
    tester.view.physicalSize = const Size(1440, 2200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final bundle = DashboardBundle(
      user: ReaderUser(
        id: 1,
        name: 'Aarav Mehta',
        username: 'aarav-mehta',
        profilePhotoUrl: null,
        email: 'aarav.mehta@example.com',
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
          category: 'Tech',
          title: 'AI Assistants Are Growing Fast',
          slug: 'ai-companies',
          imageUrl: 'https://example.com/ai.jpg',
          previewText: 'Voice and search products are shifting fast.',
          price: 65,
          accessDurationHours: 48,
          viewCount: 3620,
          unlockCount: 533,
          ratingAverage: 4.9,
          ratingCount: 24,
          authorName: 'Priya',
          isUnlocked: false,
        ),
        ArticleSummary(
          id: 2,
          category: 'Business',
          title: 'Funding Signals A Comeback',
          slug: 'startup-funding',
          imageUrl: 'https://example.com/business.jpg',
          previewText: 'Efficient growth is reopening investor conversations.',
          price: 60,
          accessDurationHours: 48,
          viewCount: 2550,
          unlockCount: 366,
          ratingAverage: 4.7,
          ratingCount: 12,
          authorName: 'Naina Sharma',
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
            baseUrl: 'http://127.0.0.1:8000',
            onRefresh: () async {},
            onOpenArticle: (_) {},
            onShareArticle: (_) {},
            onOpenProfile: () {},
          ),
        ),
      ),
    );
    await tester.pump();
    while (tester.takeException() != null) {}

    await tester.scrollUntilVisible(
      find.text('Top Articles'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Top Articles'), findsOneWidget);
  });
}
