import 'dart:io';

import 'package:file_selector/file_selector.dart' show XTypeGroup, openFile;
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../api_client.dart';
import '../article_share.dart';
import '../models.dart';
import '../reader_nav_bar.dart';
import '../reader_palette.dart';
import 'article_detail_screen.dart';

class ReaderShellScreen extends StatefulWidget {
  const ReaderShellScreen({
    super.key,
    required this.apiClient,
    required this.session,
    required this.onLoggedOut,
  });

  final ReaderApiClient apiClient;
  final ReaderSession session;
  final VoidCallback onLoggedOut;

  @override
  State<ReaderShellScreen> createState() => _ReaderShellScreenState();
}

class _ReaderShellScreenState extends State<ReaderShellScreen> {
  late Future<DashboardBundle> _dashboardFuture;
  int _tabIndex = 0;
  bool _didShowTopUpPrompt = false;
  final ImagePicker _imagePicker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _dashboardFuture = _loadDashboard();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<DashboardBundle>(
      future: _dashboardFuture,
      builder: (context, snapshot) {
        if (snapshot.hasData) {
          _maybeShowTopUpPrompt(snapshot.data!);
        }

        return Scaffold(
          body: SafeArea(
            child: snapshot.connectionState != ConnectionState.done
                ? const Center(child: CircularProgressIndicator())
                : snapshot.hasError
                ? _ErrorState(
                    message: snapshot.error.toString(),
                    onRetry: _refresh,
                    onLogout: _logout,
                  )
                : _buildLoadedState(snapshot.data!),
          ),
          bottomNavigationBar: snapshot.hasData
              ? ReaderBottomBar(
                  currentIndex: _tabIndex,
                  onChanged: _setTabIndex,
                )
              : null,
        );
      },
    );
  }

  Widget _buildLoadedState(DashboardBundle bundle) {
    switch (_tabIndex) {
      case 1:
        return ReaderSearchTab(
          bundle: bundle,
          onRefresh: _refresh,
          onOpenArticle: _openArticle,
          onShareArticle: _shareArticle,
        );
      case 2:
        return ReaderBoughtTab(
          bundle: bundle,
          onRefresh: _refresh,
          onOpenArticle: _openArticle,
          onShareArticle: _shareArticle,
        );
      case 3:
        return ReaderWalletTab(
          bundle: bundle,
          onRefresh: _refresh,
          onAddMoney: () => _addMoney(bundle.wallet),
        );
      case 4:
        return ReaderProfileTab(
          bundle: bundle,
          baseUrl: widget.session.baseUrl,
          onRefresh: _refresh,
          onChangePhoto: _changeProfilePhoto,
          onChangePassword: _changePassword,
          onLogout: _logout,
        );
      default:
        return ReaderHomeTab(
          bundle: bundle,
          baseUrl: widget.session.baseUrl,
          onRefresh: _refresh,
          onOpenArticle: _openArticle,
          onShareArticle: _shareArticle,
          onOpenProfile: () => _setTabIndex(4),
        );
    }
  }

  Future<DashboardBundle> _loadDashboard() {
    return widget.apiClient.loadDashboard(widget.session);
  }

  Future<void> _refresh() async {
    setState(() {
      _dashboardFuture = _loadDashboard();
    });

    await _dashboardFuture;
  }

  Future<void> _openArticle(ArticleSummary article) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => ArticleDetailScreen(
          apiClient: widget.apiClient,
          session: widget.session,
          article: article,
          currentTabIndex: _tabIndex,
          onNavigateToTab: _setTabIndex,
        ),
      ),
    );

    if (result == true && mounted) {
      await _refresh();
    }
  }

  Future<void> _shareArticle(ArticleSummary article) {
    return showArticleShareSheet(
      context,
      article: ArticleShareData.fromSummary(
        baseUrl: widget.session.baseUrl,
        article: article,
      ),
    );
  }

  void _setTabIndex(int index) {
    setState(() {
      _tabIndex = index;
    });
  }

  Future<void> _addMoney(WalletSummary wallet) async {
    final minimumRupees = (wallet.minPurchaseCredits / wallet.creditsPerRupee)
        .ceil();
    final controller = TextEditingController(text: '$minimumRupees');
    final rupees = await showModalBottomSheet<int>(
      context: context,
      backgroundColor: ReaderPalette.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final amount = int.tryParse(controller.text.trim()) ?? 0;
            final previewCredits = amount * wallet.creditsPerRupee;

            return Padding(
              padding: EdgeInsets.fromLTRB(
                20,
                20,
                20,
                20 + MediaQuery.of(context).viewInsets.bottom,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Add money to wallet',
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    'Enter the rupee amount. After payment succeeds, it will convert to coins at ${wallet.creditsPerRupee} coin(s) per Rs 1.',
                    style: const TextStyle(
                      color: ReaderPalette.inkMuted,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 18),
                  TextField(
                    controller: controller,
                    keyboardType: TextInputType.number,
                    onChanged: (_) => setModalState(() {}),
                    decoration: InputDecoration(
                      labelText: 'Amount (Rs)',
                      hintText: '$minimumRupees',
                      helperText:
                          'Minimum top-up: ${formatRupees(minimumRupees)}',
                    ),
                  ),
                  const SizedBox(height: 18),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.account_balance_wallet_rounded,
                            color: ReaderPalette.primary,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Conversion preview',
                                  style: TextStyle(fontWeight: FontWeight.w700),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  '${formatRupees(amount)} -> ${formatCoins(previewCredits)}',
                                  style: const TextStyle(
                                    color: ReaderPalette.inkMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () {
                        Navigator.of(context).pop(
                          int.tryParse(controller.text.trim()),
                        );
                      },
                      child: const Text('Create Payment Order'),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );

    controller.dispose();

    if (rupees == null || rupees < minimumRupees || !mounted) {
      return;
    }

    final credits = rupees * wallet.creditsPerRupee;

    try {
      final order = await widget.apiClient.createPurchaseOrder(
        widget.session,
        credits,
      );
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Order ${order.reference} created for ${formatRupees(rupees)}. ${formatCoins(order.creditAmount)} will be added after payment succeeds.',
          ),
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _logout() async {
    try {
      await widget.apiClient.logout(widget.session);
    } catch (_) {
      // Clear UI session even if the backend logout call fails.
    }

    if (mounted) {
      widget.onLoggedOut();
    }
  }

  Future<void> _changeProfilePhoto() async {
    try {
      final file = await _pickProfilePhoto();
      if (file == null || !mounted) {
        return;
      }

      await widget.apiClient.updateProfilePhoto(widget.session, file);
      if (!mounted) {
        return;
      }

      await _refresh();

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Profile photo updated successfully.')),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Could not open the photo picker. Please try again on this device.',
          ),
        ),
      );
    }
  }

  Future<XFile?> _pickProfilePhoto() {
    if (!kIsWeb && (Platform.isAndroid || Platform.isIOS)) {
      return _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 90,
        maxWidth: 2200,
      );
    }

    return openFile(
      acceptedTypeGroups: const [
        XTypeGroup(
          label: 'images',
          extensions: ['jpg', 'jpeg', 'png', 'webp'],
          mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        ),
      ],
    );
  }

  Future<void> _changePassword() async {
    final currentController = TextEditingController();
    final newController = TextEditingController();
    final confirmController = TextEditingController();
    var busy = false;
    String? errorMessage;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
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
                    Text(
                      'Change password',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'Enter your current password, then choose a new one for this account.',
                      style: TextStyle(
                        color: ReaderPalette.inkMuted,
                        height: 1.5,
                      ),
                    ),
                    if (errorMessage != null) ...[
                      const SizedBox(height: 14),
                      Text(
                        errorMessage!,
                        style: const TextStyle(
                          color: Color(0xFFB23A48),
                          height: 1.45,
                        ),
                      ),
                    ],
                    const SizedBox(height: 18),
                    TextField(
                      controller: currentController,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Current password',
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: newController,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'New password',
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: confirmController,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Confirm new password',
                      ),
                    ),
                    const SizedBox(height: 22),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: busy
                                ? null
                                : () => Navigator.of(dialogContext).pop(),
                            child: const Text('Cancel'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: busy
                                ? null
                                : () async {
                                    final currentPassword = currentController.text
                                        .trim();
                                    final newPassword = newController.text.trim();
                                    final confirmPassword = confirmController.text
                                        .trim();

                                    if (currentPassword.isEmpty ||
                                        newPassword.isEmpty ||
                                        confirmPassword.isEmpty) {
                                      setDialogState(() {
                                        errorMessage =
                                            'All password fields are required.';
                                      });
                                      return;
                                    }

                                    if (newPassword != confirmPassword) {
                                      setDialogState(() {
                                        errorMessage =
                                            'New password and confirm password must match.';
                                      });
                                      return;
                                    }

                                    setDialogState(() {
                                      busy = true;
                                      errorMessage = null;
                                    });

                                    try {
                                      await widget.apiClient.changePassword(
                                        widget.session,
                                        currentPassword: currentPassword,
                                        newPassword: newPassword,
                                        confirmPassword: confirmPassword,
                                      );

                                      if (!dialogContext.mounted || !mounted) {
                                        return;
                                      }

                                      Navigator.of(dialogContext).pop();
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        const SnackBar(
                                          content: Text(
                                            'Password updated successfully.',
                                          ),
                                        ),
                                      );
                                    } on ApiException catch (error) {
                                      if (!dialogContext.mounted) {
                                        return;
                                      }

                                      setDialogState(() {
                                        errorMessage = error.message;
                                      });
                                    } finally {
                                      if (dialogContext.mounted) {
                                        setDialogState(() {
                                          busy = false;
                                        });
                                      }
                                    }
                                  },
                            child: Text(
                              busy ? 'Updating...' : 'Update Password',
                            ),
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
      },
    );

    currentController.dispose();
    newController.dispose();
    confirmController.dispose();
  }

  void _maybeShowTopUpPrompt(DashboardBundle bundle) {
    if (_didShowTopUpPrompt) {
      return;
    }

    _didShowTopUpPrompt = true;

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      if (!mounted) {
        return;
      }

      final action = await showDialog<_TopUpPromptAction>(
        context: context,
        builder: (context) => _PostLoginTopUpDialog(bundle: bundle),
      );

      if (!mounted || action != _TopUpPromptAction.recharge) {
        return;
      }

      _setTabIndex(3);

      await _addMoney(bundle.wallet);
    });
  }
}

enum _TopUpPromptAction { recharge, later }

class ReaderHomeTab extends StatefulWidget {
  const ReaderHomeTab({
    super.key,
    required this.bundle,
    required this.baseUrl,
    required this.onRefresh,
    required this.onOpenArticle,
    required this.onShareArticle,
    required this.onOpenProfile,
  });

  final DashboardBundle bundle;
  final String baseUrl;
  final Future<void> Function() onRefresh;
  final ValueChanged<ArticleSummary> onOpenArticle;
  final ValueChanged<ArticleSummary> onShareArticle;
  final VoidCallback onOpenProfile;

  @override
  State<ReaderHomeTab> createState() => _ReaderHomeTabState();
}

class _ReaderHomeTabState extends State<ReaderHomeTab> {
  late final TextEditingController _searchController;
  String _query = '';
  String _selectedCategory = 'All';
  String _selectedAuthor = 'All authors';

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final categoryOptions = {
      for (final article in widget.bundle.articles)
        article.category.trim().isEmpty ? 'General' : article.category.trim(),
    }.toList()..sort();
    final authorOptions = {
      for (final article in widget.bundle.articles) article.authorName,
    }.toList()..sort();
    final categories = <String>['All', ...categoryOptions];
    final authors = <String>['All authors', ...authorOptions];
    final selectedCategory = categories.contains(_selectedCategory)
        ? _selectedCategory
        : 'All';
    final selectedAuthor = authors.contains(_selectedAuthor)
        ? _selectedAuthor
        : 'All authors';

    final filteredArticles = widget.bundle.articles.where((article) {
      final needle = _query.trim().toLowerCase();
      final category = article.category.trim().isEmpty
          ? 'General'
          : article.category.trim();

      final matchesSearch =
          needle.isEmpty ||
          article.title.toLowerCase().contains(needle) ||
          article.authorName.toLowerCase().contains(needle) ||
          article.previewText.toLowerCase().contains(needle) ||
          category.toLowerCase().contains(needle);
      final matchesCategory =
          selectedCategory == 'All' || category == selectedCategory;
      final matchesAuthor =
          selectedAuthor == 'All authors' ||
          article.authorName == selectedAuthor;

      return matchesSearch && matchesCategory && matchesAuthor;
    }).toList();
    final topArticles = [...filteredArticles]..sort(_compareTopArticles);
    final latestArticles = filteredArticles.take(6).toList();
    final leadArticle = topArticles.isNotEmpty ? topArticles.first : null;
    final highlightedArticles = topArticles
        .where((article) => article.id != leadArticle?.id)
        .take(4)
        .toList();
    final quickPickArticles = filteredArticles
        .where((article) => article.id != leadArticle?.id)
        .take(5)
        .toList();
    final categoryGroups = _buildCategoryGroups(
      filteredArticles: filteredArticles,
      selectedCategory: selectedCategory,
    );

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final isWide = constraints.maxWidth >= 1040;

          return CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverPersistentHeader(
                pinned: true,
                delegate: _HomeSearchHeaderDelegate(
                  bundle: widget.bundle,
                  baseUrl: widget.baseUrl,
                  controller: _searchController,
                  query: _query,
                  onChanged: (value) {
                    setState(() {
                      _query = value;
                    });
                  },
                  onClear: () {
                    _searchController.clear();
                    setState(() {
                      _query = '';
                    });
                  },
                  onOpenProfile: widget.onOpenProfile,
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 18, 16, 124),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _HomeSummaryCard(bundle: widget.bundle),
                      const SizedBox(height: 18),
                      _CategorySubHeader(
                        options: categories,
                        selectedCategory: selectedCategory,
                        onSelected: (category) {
                          setState(() {
                            _selectedCategory = category;
                          });
                        },
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: _FeedFilterDropdown(
                              label: 'By author',
                              value: selectedAuthor,
                              options: authors,
                              onChanged: (value) {
                                if (value == null) {
                                  return;
                                }

                                setState(() {
                                  _selectedAuthor = value;
                                });
                              },
                            ),
                          ),
                          const SizedBox(width: 12),
                          SizedBox(
                            height: 56,
                            child: OutlinedButton.icon(
                              onPressed:
                                  _query.isEmpty &&
                                      selectedCategory == 'All' &&
                                      selectedAuthor == 'All authors'
                                  ? null
                                  : () {
                                      _searchController.clear();
                                      setState(() {
                                        _query = '';
                                        _selectedCategory = 'All';
                                        _selectedAuthor = 'All authors';
                                      });
                                    },
                              icon: const Icon(Icons.filter_alt_off_rounded),
                              label: const Text('Reset'),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),
                      if (filteredArticles.isEmpty)
                        const _EmptyCard(
                          message:
                              'No stories match your current search or filters yet.',
                        )
                      else ...[
                        _SectionTitle(
                          title: 'Top Articles',
                          actionLabel: '${topArticles.length} ranked',
                        ),
                        const SizedBox(height: 12),
                        if (leadArticle != null)
                          if (isWide)
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  flex: 3,
                                  child: _HeroArticleCard(
                                    article: leadArticle,
                                    wideLayout: true,
                                    onTap: () =>
                                        widget.onOpenArticle(leadArticle),
                                    onShare: () =>
                                        widget.onShareArticle(leadArticle),
                                  ),
                                ),
                                const SizedBox(width: 16),
                                Expanded(
                                  flex: 2,
                                  child: Column(
                                    children: highlightedArticles
                                        .map(
                                          (article) => Padding(
                                            padding: const EdgeInsets.only(
                                              bottom: 12,
                                            ),
                                            child: _ArticleRailTile(
                                              article: article,
                                              emphasized: true,
                                              onTap: () =>
                                                  widget.onOpenArticle(article),
                                              onShare: () => widget
                                                  .onShareArticle(article),
                                            ),
                                          ),
                                        )
                                        .toList(growable: false),
                                  ),
                                ),
                              ],
                            )
                          else ...[
                            _HeroArticleCard(
                              article: leadArticle,
                              wideLayout: false,
                              onTap: () => widget.onOpenArticle(leadArticle),
                              onShare: () =>
                                  widget.onShareArticle(leadArticle),
                            ),
                            if (highlightedArticles.isNotEmpty) ...[
                              const SizedBox(height: 14),
                              ...highlightedArticles.map(
                                (article) => Padding(
                                  padding: const EdgeInsets.only(bottom: 12),
                                  child: _ArticleRailTile(
                                    article: article,
                                    emphasized: false,
                                    onTap: () =>
                                        widget.onOpenArticle(article),
                                    onShare: () =>
                                        widget.onShareArticle(article),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        if (quickPickArticles.isNotEmpty) ...[
                          const SizedBox(height: 26),
                          _SectionTitle(
                            title: 'Quick Picks',
                            actionLabel: '${quickPickArticles.length} stories',
                          ),
                          const SizedBox(height: 12),
                          _HorizontalArticleScroller(
                            articles: quickPickArticles,
                            onOpenArticle: widget.onOpenArticle,
                            onShareArticle: widget.onShareArticle,
                          ),
                        ],
                        const SizedBox(height: 26),
                        _SectionTitle(
                          title: 'Latest Articles',
                          actionLabel: '${latestArticles.length} fresh',
                        ),
                        const SizedBox(height: 12),
                        ...latestArticles.map(
                          (article) => Padding(
                            padding: const EdgeInsets.only(bottom: 14),
                            child: _ArticleCard(
                              article: article,
                              onTap: () => widget.onOpenArticle(article),
                              onShare: () =>
                                  widget.onShareArticle(article),
                            ),
                          ),
                        ),
                        if (categoryGroups.isNotEmpty) ...[
                          const SizedBox(height: 12),
                          ...categoryGroups.map(
                            (group) => Padding(
                              padding: const EdgeInsets.only(bottom: 26),
                              child: _CategoryStorySection(
                                title: group.category,
                                articles: group.articles,
                                onOpenArticle: widget.onOpenArticle,
                                onShareArticle: widget.onShareArticle,
                              ),
                            ),
                          ),
                        ],
                        _SectionTitle(
                          title: 'All Articles',
                          actionLabel: '${filteredArticles.length} total',
                        ),
                        const SizedBox(height: 12),
                        ...filteredArticles.map(
                          (article) => Padding(
                            padding: const EdgeInsets.only(bottom: 14),
                            child: _ArticleCard(
                              article: article,
                              onTap: () => widget.onOpenArticle(article),
                              onShare: () =>
                                  widget.onShareArticle(article),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  List<_CategoryStoryGroup> _buildCategoryGroups({
    required List<ArticleSummary> filteredArticles,
    required String selectedCategory,
  }) {
    final orderedCategories = <String>[];
    for (final article in filteredArticles) {
      if (!orderedCategories.contains(article.category)) {
        orderedCategories.add(article.category);
      }
    }

    final selectedCategories = selectedCategory == 'All'
        ? orderedCategories.take(4)
        : [selectedCategory];

    return selectedCategories
        .map((category) {
          final articles = filteredArticles
              .where((article) => article.category == category)
              .take(6)
              .toList();

          return _CategoryStoryGroup(category: category, articles: articles);
        })
        .where((group) => group.articles.isNotEmpty)
        .toList(growable: false);
  }

  int _compareTopArticles(ArticleSummary left, ArticleSummary right) {
    final rating = right.ratingAverage.compareTo(left.ratingAverage);
    if (rating != 0) {
      return rating;
    }

    final ratingCount = right.ratingCount.compareTo(left.ratingCount);
    if (ratingCount != 0) {
      return ratingCount;
    }

    final unlocks = right.unlockCount.compareTo(left.unlockCount);
    if (unlocks != 0) {
      return unlocks;
    }

    return right.viewCount.compareTo(left.viewCount);
  }
}

class _CategoryStoryGroup {
  const _CategoryStoryGroup({required this.category, required this.articles});

  final String category;
  final List<ArticleSummary> articles;
}

class _BoughtArticleItem {
  const _BoughtArticleItem({
    required this.article,
    required this.creditsSpent,
    required this.unlockedAt,
    required this.expiresAt,
    required this.isActive,
  });

  final ArticleSummary article;
  final int creditsSpent;
  final DateTime? unlockedAt;
  final DateTime? expiresAt;
  final bool isActive;
}

class ReaderSearchTab extends StatefulWidget {
  const ReaderSearchTab({
    super.key,
    required this.bundle,
    required this.onRefresh,
    required this.onOpenArticle,
    required this.onShareArticle,
  });

  final DashboardBundle bundle;
  final Future<void> Function() onRefresh;
  final ValueChanged<ArticleSummary> onOpenArticle;
  final ValueChanged<ArticleSummary> onShareArticle;

  @override
  State<ReaderSearchTab> createState() => _ReaderSearchTabState();
}

class _ReaderSearchTabState extends State<ReaderSearchTab> {
  late final TextEditingController _searchController;
  String _query = '';
  String _selectedCategory = 'All';

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final categoryOptions = {
      for (final article in widget.bundle.articles)
        article.category.trim().isEmpty ? 'General' : article.category.trim(),
    }.toList()..sort();
    final categories = <String>['All', ...categoryOptions];
    final selectedCategory = categories.contains(_selectedCategory)
        ? _selectedCategory
        : 'All';

    final filteredArticles = widget.bundle.articles.where((article) {
      final category = article.category.trim().isEmpty
          ? 'General'
          : article.category.trim();
      final needle = _query.trim().toLowerCase();
      final matchesQuery =
          needle.isEmpty ||
          article.title.toLowerCase().contains(needle) ||
          article.authorName.toLowerCase().contains(needle) ||
          article.previewText.toLowerCase().contains(needle) ||
          category.toLowerCase().contains(needle);
      final matchesCategory =
          selectedCategory == 'All' || category == selectedCategory;

      return matchesQuery && matchesCategory;
    }).toList(growable: false);

    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 124),
        children: [
          _TopHeader(
            title: 'Search',
            trailing: IconButton(
              onPressed: () => widget.onRefresh(),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ),
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: _searchController,
                    onChanged: (value) {
                      setState(() {
                        _query = value;
                      });
                    },
                    decoration: InputDecoration(
                      prefixIcon: const Icon(Icons.search_rounded),
                      hintText: 'Search by title, author, category, or story',
                      suffixIcon: _query.isEmpty
                          ? null
                          : IconButton(
                              onPressed: () {
                                _searchController.clear();
                                setState(() {
                                  _query = '';
                                });
                              },
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Use search to quickly jump to any article in the reader app.',
                    style: TextStyle(
                      color: ReaderPalette.inkMuted,
                      height: 1.5,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 18),
          _CategorySubHeader(
            options: categories,
            selectedCategory: selectedCategory,
            onSelected: (category) {
              setState(() {
                _selectedCategory = category;
              });
            },
          ),
          const SizedBox(height: 24),
          _SectionTitle(
            title: _query.trim().isEmpty ? 'Browse Results' : 'Search Results',
            actionLabel: '${filteredArticles.length} found',
          ),
          const SizedBox(height: 12),
          if (filteredArticles.isEmpty)
            const _EmptyCard(
              message: 'No articles matched this search yet. Try another title or category.',
            )
          else
            ...filteredArticles.map(
              (article) => Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: _ArticleCard(
                  article: article,
                  onTap: () => widget.onOpenArticle(article),
                  onShare: () => widget.onShareArticle(article),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class ReaderBoughtTab extends StatelessWidget {
  const ReaderBoughtTab({
    super.key,
    required this.bundle,
    required this.onRefresh,
    required this.onOpenArticle,
    required this.onShareArticle,
  });

  final DashboardBundle bundle;
  final Future<void> Function() onRefresh;
  final ValueChanged<ArticleSummary> onOpenArticle;
  final ValueChanged<ArticleSummary> onShareArticle;

  @override
  Widget build(BuildContext context) {
    final boughtItems = _buildBoughtArticles(bundle);
    final activeCount = boughtItems.where((item) => item.isActive).length;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 124),
        children: [
          _TopHeader(
            title: 'Bought',
            trailing: IconButton(
              onPressed: () => onRefresh(),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ),
          const SizedBox(height: 18),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Your unlocked articles',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'See every article this user has already bought or unlocked from the backend.',
                    style: TextStyle(
                      color: ReaderPalette.inkMuted,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      _StatChip(
                        icon: Icons.bookmark_rounded,
                        label: '${boughtItems.length} bought',
                        dark: false,
                      ),
                      _StatChip(
                        icon: Icons.verified_rounded,
                        label: '$activeCount active',
                        dark: false,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(
            title: 'Bought Articles',
            actionLabel: '${boughtItems.length} total',
          ),
          const SizedBox(height: 12),
          if (boughtItems.isEmpty)
            const _EmptyCard(
              message: 'This user has not bought any premium articles yet.',
            )
          else
            ...boughtItems.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: _BoughtArticleCard(
                  item: item,
                  onOpen: () => onOpenArticle(item.article),
                  onShare: () => onShareArticle(item.article),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class ReaderWalletTab extends StatelessWidget {
  const ReaderWalletTab({
    super.key,
    required this.bundle,
    required this.onRefresh,
    required this.onAddMoney,
  });

  final DashboardBundle bundle;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onAddMoney;

  @override
  Widget build(BuildContext context) {
    final transactions = bundle.transactions.take(8).toList();

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 124),
        children: [
          _TopHeader(
            title: 'Wallet',
            trailing: IconButton(
              onPressed: () => onRefresh(),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ),
          const SizedBox(height: 18),
          _WalletBalanceCard(bundle: bundle),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: ElevatedButton(
                  onPressed: () => onAddMoney(),
                  child: const Text('Add Money'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => onRefresh(),
                  child: const Text('Refresh'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          const Card(
            child: Padding(
              padding: EdgeInsets.all(18),
              child: Text(
                'Add Money creates a payment order first. Once the payment is confirmed, the amount is converted into coins and added to this wallet.',
                style: TextStyle(color: ReaderPalette.inkMuted, height: 1.6),
              ),
            ),
          ),
          const SizedBox(height: 24),
          _SectionTitle(
            title: 'Recent Transactions',
            actionLabel: '${bundle.transactions.length} total',
          ),
          const SizedBox(height: 12),
          if (transactions.isEmpty)
            const _EmptyCard(message: 'No wallet activity found yet.')
          else
            ...transactions.map(
              (transaction) => Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: _TransactionCard(transaction: transaction),
              ),
            ),
        ],
      ),
    );
  }
}

class ReaderProfileTab extends StatelessWidget {
  const ReaderProfileTab({
    super.key,
    required this.bundle,
    required this.baseUrl,
    required this.onRefresh,
    required this.onChangePhoto,
    required this.onChangePassword,
    required this.onLogout,
  });

  final DashboardBundle bundle;
  final String baseUrl;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onChangePhoto;
  final Future<void> Function() onChangePassword;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 124),
        children: [
          _TopHeader(
            title: 'Profile',
            trailing: IconButton(
              onPressed: () => onRefresh(),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ),
          const SizedBox(height: 18),
          _ProfileHero(bundle: bundle, baseUrl: baseUrl),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => onChangePhoto(),
                  icon: const Icon(Icons.add_a_photo_outlined),
                  label: Text(
                    bundle.user.profilePhotoUrl == null
                        ? 'Upload Photo'
                        : 'Change Photo',
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => onChangePassword(),
                  icon: const Icon(Icons.lock_reset_rounded),
                  label: const Text('Change Password'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          if (bundle.user.username != null) ...[
            _InfoRow(label: 'Username', value: bundle.user.username!),
            const SizedBox(height: 12),
          ],
          _InfoRow(label: 'Email', value: bundle.user.email),
          const SizedBox(height: 12),
          _InfoRow(label: 'Phone', value: bundle.user.phone ?? 'Not added yet'),
          const SizedBox(height: 12),
          _InfoRow(
            label: 'Joined',
            value: bundle.user.createdAt == null
                ? 'Recently'
                : formatDate(bundle.user.createdAt!),
          ),
          const SizedBox(height: 22),
          OutlinedButton.icon(
            onPressed: () => onLogout(),
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Logout'),
          ),
        ],
      ),
    );
  }
}

class _TopHeader extends StatelessWidget {
  const _TopHeader({required this.title, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
        ?trailing,
      ],
    );
  }
}

class _HomeSearchHeaderDelegate extends SliverPersistentHeaderDelegate {
  const _HomeSearchHeaderDelegate({
    required this.bundle,
    required this.baseUrl,
    required this.controller,
    required this.query,
    required this.onChanged,
    required this.onClear,
    required this.onOpenProfile,
  });

  final DashboardBundle bundle;
  final String baseUrl;
  final TextEditingController controller;
  final String query;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;
  final VoidCallback onOpenProfile;

  @override
  double get minExtent => 96;

  @override
  double get maxExtent => 96;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Container(
      color: ReaderPalette.canvas,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
      child: _HomeSearchHeader(
        bundle: bundle,
        baseUrl: baseUrl,
        controller: controller,
        query: query,
        onChanged: onChanged,
        onClear: onClear,
        onOpenProfile: onOpenProfile,
      ),
    );
  }

  @override
  bool shouldRebuild(covariant _HomeSearchHeaderDelegate oldDelegate) {
    return bundle != oldDelegate.bundle ||
        baseUrl != oldDelegate.baseUrl ||
        controller != oldDelegate.controller ||
        query != oldDelegate.query;
  }
}

class _HomeSearchHeader extends StatelessWidget {
  const _HomeSearchHeader({
    required this.bundle,
    required this.baseUrl,
    required this.controller,
    required this.query,
    required this.onChanged,
    required this.onClear,
    required this.onOpenProfile,
  });

  final DashboardBundle bundle;
  final String baseUrl;
  final TextEditingController controller;
  final String query;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;
  final VoidCallback onOpenProfile;

  @override
  Widget build(BuildContext context) {
    final profilePhotoUrl = resolveReaderAssetUrl(
      baseUrl,
      bundle.user.profilePhotoUrl,
    );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: TextField(
                controller: controller,
                onChanged: onChanged,
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.search_rounded),
                  hintText: 'Search articles, authors, or categories',
                  suffixIcon: query.isEmpty
                      ? null
                      : IconButton(
                          onPressed: onClear,
                          icon: const Icon(Icons.close_rounded),
                        ),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: onOpenProfile,
                customBorder: const CircleBorder(),
                child: Padding(
                  padding: const EdgeInsets.all(2),
                  child: CircleAvatar(
                    radius: 24,
                    backgroundColor: ReaderPalette.primarySoft,
                    backgroundImage: profilePhotoUrl == null
                        ? null
                        : NetworkImage(profilePhotoUrl),
                    child: profilePhotoUrl == null
                        ? Text(
                            bundle.user.initials,
                            style: const TextStyle(
                              color: ReaderPalette.inverseText,
                              fontWeight: FontWeight.w700,
                            ),
                          )
                        : null,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeSummaryCard extends StatelessWidget {
  const _HomeSummaryCard({required this.bundle});

  final DashboardBundle bundle;

  @override
  Widget build(BuildContext context) {
    final premiumCount = bundle.articles
        .where((article) => !article.isUnlocked)
        .length;
    final categoryCount = {
      for (final article in bundle.articles) article.category,
    }.length;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            _StatChip(
              icon: Icons.menu_book_rounded,
              label: '${bundle.articles.length} stories',
              dark: false,
            ),
            _StatChip(
              icon: Icons.lock_outline_rounded,
              label: '$premiumCount premium',
              dark: false,
            ),
            _StatChip(
              icon: Icons.dashboard_customize_rounded,
              label: '$categoryCount categories',
              dark: false,
            ),
            _StatChip(
              icon: Icons.account_balance_wallet_rounded,
              label: formatCoins(bundle.wallet.walletBalance),
              dark: false,
            ),
          ],
        ),
      ),
    );
  }
}

class _CategorySubHeader extends StatelessWidget {
  const _CategorySubHeader({
    required this.options,
    required this.selectedCategory,
    required this.onSelected,
  });

  final List<String> options;
  final String selectedCategory;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: options.length,
        separatorBuilder: (_, _) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final option = options[index];
          final selected = option == selectedCategory;

          return GestureDetector(
            onTap: () => onSelected(option),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 11),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(999),
                color: selected ? ReaderPalette.primary : ReaderPalette.surface,
                border: Border.all(
                  color: selected
                      ? ReaderPalette.primary
                      : ReaderPalette.border,
                ),
              ),
              child: Text(
                option,
                style: TextStyle(
                  color: selected
                      ? ReaderPalette.inverseText
                      : ReaderPalette.ink,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _HeroArticleCard extends StatelessWidget {
  const _HeroArticleCard({
    required this.article,
    required this.wideLayout,
    required this.onTap,
    required this.onShare,
  });

  final ArticleSummary article;
  final bool wideLayout;
  final VoidCallback onTap;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    final content = Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _OverlayChip(label: article.category),
                    _OverlayChip(
                      label: article.isUnlocked
                          ? 'Unlocked'
                          : formatCoins(article.price),
                      highlighted: article.isUnlocked,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              _ArticleShareButton(
                onPressed: onShare,
                foregroundColor: ReaderPalette.inverseText,
                backgroundColor: ReaderPalette.darkSurfaceSoft,
                borderColor: Colors.white.withValues(alpha: 0.08),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            article.title,
            maxLines: wideLayout ? 3 : 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800,
              color: ReaderPalette.inverseText,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            article.previewText,
            maxLines: wideLayout ? 4 : 3,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: ReaderPalette.inverseMuted,
              height: 1.6,
            ),
          ),
          const Spacer(),
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: ReaderPalette.primarySoft,
                child: Text(
                  initialsFor(article.authorName),
                  style: const TextStyle(
                    color: ReaderPalette.inverseText,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      article.authorName,
                      style: const TextStyle(
                        color: ReaderPalette.inverseText,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      '${article.viewCount} views • ${article.unlockCount} unlocks',
                      style: const TextStyle(
                        color: ReaderPalette.inverseMuted,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              _ReadMoreButton(onTap: onTap, dark: true),
            ],
          ),
        ],
      ),
    );

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(30),
      child: Container(
        decoration: BoxDecoration(
          color: ReaderPalette.darkSurface,
          borderRadius: BorderRadius.circular(30),
          boxShadow: ReaderPalette.softShadow,
        ),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final horizontal = wideLayout && constraints.maxWidth > 560;
            final image = ClipRRect(
              borderRadius: horizontal
                  ? const BorderRadius.horizontal(left: Radius.circular(30))
                  : const BorderRadius.vertical(top: Radius.circular(30)),
              child: SizedBox(
                width: horizontal ? 240 : double.infinity,
                height: horizontal ? 320 : 190,
                child: article.imageUrl != null
                    ? Image.network(
                        article.imageUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            _HeroFallback(title: article.title),
                      )
                    : _HeroFallback(title: article.title),
              ),
            );

            if (horizontal) {
              return SizedBox(
                height: 320,
                child: Row(children: [image, Expanded(child: content)]),
              );
            }

            return Column(
              children: [image, SizedBox(height: 280, child: content)],
            );
          },
        ),
      ),
    );
  }
}

class _ArticleRailTile extends StatelessWidget {
  const _ArticleRailTile({
    required this.article,
    required this.emphasized,
    required this.onTap,
    required this.onShare,
  });

  final ArticleSummary article;
  final bool emphasized;
  final VoidCallback onTap;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: BoxDecoration(
          color: emphasized ? ReaderPalette.darkSurface : ReaderPalette.surface,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(
            color: emphasized
                ? Colors.white.withValues(alpha: 0.06)
                : ReaderPalette.border,
          ),
          boxShadow: ReaderPalette.softShadow,
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: SizedBox(
                  width: 112,
                  height: 118,
                  child: article.imageUrl != null
                      ? Image.network(
                          article.imageUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              _ArticleImageFallback(
                                title: article.title,
                                height: 118,
                              ),
                        )
                      : _ArticleImageFallback(
                          title: article.title,
                          height: 118,
                        ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              _MiniBadge(
                                label: article.category,
                                dark: emphasized,
                              ),
                              _MiniBadge(
                                label: article.isUnlocked
                                    ? 'Unlocked'
                                    : formatCoins(article.price),
                                dark: emphasized,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 10),
                        _ArticleShareButton(onPressed: onShare),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      article.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        height: 1.25,
                        color: emphasized
                            ? ReaderPalette.inverseText
                            : ReaderPalette.ink,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      article.previewText,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: emphasized
                            ? ReaderPalette.inverseMuted
                            : ReaderPalette.inkMuted,
                        height: 1.45,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            article.authorName,
                            style: TextStyle(
                              color: emphasized
                                  ? ReaderPalette.inverseMuted
                                  : ReaderPalette.inkMuted,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        _ReadMoreButton(onTap: onTap, dark: emphasized),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HorizontalArticleScroller extends StatelessWidget {
  const _HorizontalArticleScroller({
    required this.articles,
    required this.onOpenArticle,
    required this.onShareArticle,
  });

  final List<ArticleSummary> articles;
  final ValueChanged<ArticleSummary> onOpenArticle;
  final ValueChanged<ArticleSummary> onShareArticle;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 452,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: articles.length,
        separatorBuilder: (_, _) => const SizedBox(width: 14),
        itemBuilder: (context, index) {
          final article = articles[index];

          return SizedBox(
            width: 252,
            child: _CompactArticleCard(
              article: article,
              onTap: () => onOpenArticle(article),
              onShare: () => onShareArticle(article),
            ),
          );
        },
      ),
    );
  }
}

class _CompactArticleCard extends StatelessWidget {
  const _CompactArticleCard({
    required this.article,
    required this.onTap,
    required this.onShare,
  });

  final ArticleSummary article;
  final VoidCallback onTap;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(24),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: SizedBox(
                  height: 96,
                  width: double.infinity,
                  child: article.imageUrl != null
                      ? Image.network(
                        article.imageUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            _ArticleImageFallback(title: article.title),
                      )
                      : _ArticleImageFallback(title: article.title),
                ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _MiniBadge(label: article.category),
                  _MiniBadge(
                    label: article.isUnlocked
                        ? 'Unlocked'
                        : formatCoins(article.price),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                article.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                article.previewText,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: ReaderPalette.inkMuted,
                  fontSize: 13,
                  height: 1.45,
                ),
              ),
              const SizedBox(height: 10),
              _RatingBadge(
                ratingAverage: article.ratingAverage,
                ratingCount: article.ratingCount,
              ),
              const SizedBox(height: 10),
              Text(
                article.authorName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: ReaderPalette.inkMuted,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const Spacer(),
              Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _ReadMoreButton(onTap: onTap),
                  const SizedBox(width: 10),
                  _ArticleShareButton(onPressed: onShare),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CategoryStorySection extends StatelessWidget {
  const _CategoryStorySection({
    required this.title,
    required this.articles,
    required this.onOpenArticle,
    required this.onShareArticle,
  });

  final String title;
  final List<ArticleSummary> articles;
  final ValueChanged<ArticleSummary> onOpenArticle;
  final ValueChanged<ArticleSummary> onShareArticle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _SectionTitle(
          title: '$title Articles',
          actionLabel: '${articles.length} stories',
        ),
        const SizedBox(height: 12),
        _HorizontalArticleScroller(
          articles: articles,
          onOpenArticle: onOpenArticle,
          onShareArticle: onShareArticle,
        ),
      ],
    );
  }
}

class _OverlayChip extends StatelessWidget {
  const _OverlayChip({required this.label, this.highlighted = false});

  final String label;
  final bool highlighted;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: highlighted
            ? ReaderPalette.success.withValues(alpha: 0.20)
            : ReaderPalette.surface.withValues(alpha: 0.18),
        border: Border.all(
          color: highlighted
              ? ReaderPalette.success.withValues(alpha: 0.7)
              : ReaderPalette.secondary.withValues(alpha: 0.32),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: ReaderPalette.inverseText,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _RatingBadge extends StatelessWidget {
  const _RatingBadge({
    required this.ratingAverage,
    required this.ratingCount,
  });

  final double ratingAverage;
  final int ratingCount;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 6,
      runSpacing: 4,
      crossAxisAlignment: WrapCrossAlignment.center,
      children: [
        const Icon(Icons.star_rounded, color: Color(0xFFFFC94A), size: 18),
        Text(
          formatRating(ratingAverage),
          style: const TextStyle(
            color: ReaderPalette.ink,
            fontWeight: FontWeight.w700,
          ),
        ),
        Text(
          '($ratingCount ratings)',
          style: const TextStyle(
            color: ReaderPalette.inkMuted,
            fontSize: 13,
          ),
        ),
      ],
    );
  }
}

class _HeroFallback extends StatelessWidget {
  const _HeroFallback({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            ReaderPalette.primary,
            ReaderPalette.primarySoft,
            ReaderPalette.secondary,
          ],
        ),
      ),
      alignment: Alignment.bottomLeft,
      padding: const EdgeInsets.all(24),
      child: Text(
        title,
        maxLines: 3,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: ReaderPalette.inverseText,
          fontWeight: FontWeight.w800,
          fontSize: 26,
          height: 1.2,
        ),
      ),
    );
  }
}

class _ArticleCard extends StatelessWidget {
  const _ArticleCard({
    required this.article,
    required this.onTap,
    required this.onShare,
  });

  final ArticleSummary article;
  final VoidCallback onTap;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: BoxDecoration(
          color: ReaderPalette.surface,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: ReaderPalette.border),
          boxShadow: ReaderPalette.softShadow,
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (article.imageUrl != null)
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: SizedBox(
                    height: 164,
                    width: double.infinity,
                    child: Image.network(
                      article.imageUrl!,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) =>
                          _ArticleImageFallback(title: article.title),
                    ),
                  ),
                )
              else
                _ArticleImageFallback(title: article.title),
              const SizedBox(height: 16),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _MiniBadge(label: article.category),
                  _MiniBadge(
                    label: article.isUnlocked
                        ? 'Unlocked'
                        : formatCoins(article.price),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Text(
                article.title,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  height: 1.2,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                article.previewText,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: ReaderPalette.inkMuted,
                  height: 1.55,
                ),
              ),
              const SizedBox(height: 12),
              _RatingBadge(
                ratingAverage: article.ratingAverage,
                ratingCount: article.ratingCount,
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: Text(
                      article.authorName,
                      style: const TextStyle(
                        color: ReaderPalette.ink,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  _ReadMoreButton(onTap: onTap),
                  const SizedBox(width: 10),
                  _ArticleShareButton(onPressed: onShare),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ArticleShareButton extends StatelessWidget {
  const _ArticleShareButton({
    required this.onPressed,
    this.foregroundColor = ReaderPalette.primary,
    this.backgroundColor = ReaderPalette.surfaceMuted,
    this.borderColor = ReaderPalette.border,
  });

  final VoidCallback onPressed;
  final Color foregroundColor;
  final Color backgroundColor;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: backgroundColor,
        shape: BoxShape.circle,
        border: Border.all(color: borderColor),
      ),
      child: IconButton(
        onPressed: onPressed,
        tooltip: 'Share article',
        constraints: const BoxConstraints(minWidth: 40, minHeight: 40),
        padding: EdgeInsets.zero,
        visualDensity: VisualDensity.compact,
        splashRadius: 20,
        icon: Icon(Icons.share_outlined, size: 18, color: foregroundColor),
      ),
    );
  }
}

class _WalletBalanceCard extends StatelessWidget {
  const _WalletBalanceCard({required this.bundle});

  final DashboardBundle bundle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: ReaderPalette.editorialGradient,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Wallet Balance',
            style: TextStyle(
              color: ReaderPalette.inverseMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 16),
          Text(
            formatCoins(bundle.wallet.walletBalance),
            style: Theme.of(
              context,
            ).textTheme.headlineMedium?.copyWith(
              fontWeight: FontWeight.w800,
              color: ReaderPalette.inverseText,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Updated ${formatDate(bundle.loadedAt)}',
            style: const TextStyle(color: ReaderPalette.inverseMuted),
          ),
        ],
      ),
    );
  }
}

class _TransactionCard extends StatelessWidget {
  const _TransactionCard({required this.transaction});

  final WalletTransaction transaction;

  @override
  Widget build(BuildContext context) {
    final isPositive = transaction.isCredit;
    final accent = isPositive
        ? ReaderPalette.success
        : ReaderPalette.primarySoft;
    final icon = isPositive ? Icons.check_rounded : Icons.north_east_rounded;
    final amountText = formatCoinDelta(
      transaction.amount,
      isPositive: isPositive,
    );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                color: accent.withValues(alpha: 0.14),
              ),
              child: Icon(icon, color: accent),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    transactionTitle(transaction),
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    transaction.createdAt == null
                        ? 'Just now'
                        : formatDate(transaction.createdAt!),
                    style: const TextStyle(color: ReaderPalette.inkMuted),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  amountText,
                  style: TextStyle(
                    color: accent,
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  transaction.status.toUpperCase(),
                  style: const TextStyle(color: ReaderPalette.inkMuted),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ProfileHero extends StatelessWidget {
  const _ProfileHero({required this.bundle, required this.baseUrl});

  final DashboardBundle bundle;
  final String baseUrl;

  @override
  Widget build(BuildContext context) {
    final profilePhotoUrl = resolveReaderAssetUrl(
      baseUrl,
      bundle.user.profilePhotoUrl,
    );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 38,
              backgroundColor: ReaderPalette.primary,
              backgroundImage: profilePhotoUrl == null
                  ? null
                  : NetworkImage(profilePhotoUrl),
              child: profilePhotoUrl == null
                  ? Text(
                      bundle.user.initials,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 20,
                        color: ReaderPalette.inverseText,
                      ),
                    )
                  : null,
            ),
            const SizedBox(height: 18),
            Text(
              bundle.user.name,
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(
              bundle.user.email,
              style: const TextStyle(color: ReaderPalette.inkMuted),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _MiniBadge(label: capitalize(bundle.user.role)),
                _MiniBadge(label: formatCoins(bundle.wallet.walletBalance)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 20),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(color: ReaderPalette.inkMuted),
              ),
            ),
            Expanded(
              child: Text(
                value,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.actionLabel});

  final String title;
  final String actionLabel;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          actionLabel,
          style: const TextStyle(color: ReaderPalette.inkMuted),
        ),
      ],
    );
  }
}

class _FeedFilterDropdown extends StatelessWidget {
  const _FeedFilterDropdown({
    required this.label,
    required this.value,
    required this.options,
    required this.onChanged,
  });

  final String label;
  final String value;
  final List<String> options;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      key: ValueKey<String>(value),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      dropdownColor: ReaderPalette.surface,
      items: options
          .map(
            (option) => DropdownMenuItem<String>(
              value: option,
              child: Text(option, overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(growable: false),
      onChanged: onChanged,
    );
  }
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Text(
          message,
          style: const TextStyle(color: ReaderPalette.inkMuted, height: 1.6),
        ),
      ),
    );
  }
}

class _MiniBadge extends StatelessWidget {
  const _MiniBadge({required this.label, this.dark = false});

  final String label;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(999),
        color: dark
            ? Colors.white.withValues(alpha: 0.06)
            : ReaderPalette.surfaceMuted,
        border: Border.all(
          color: dark
              ? Colors.white.withValues(alpha: 0.08)
              : ReaderPalette.border,
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: dark ? ReaderPalette.inverseText : ReaderPalette.ink,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _StatChip extends StatelessWidget {
  const _StatChip({
    required this.icon,
    required this.label,
    this.dark = true,
  });

  final IconData icon;
  final String label;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        color: dark ? ReaderPalette.darkSurfaceSoft : ReaderPalette.surfaceMuted,
        border: Border.all(
          color: dark
              ? Colors.white.withValues(alpha: 0.08)
              : ReaderPalette.border,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            icon,
            color: dark ? ReaderPalette.secondary : ReaderPalette.primary,
            size: 18,
          ),
          const SizedBox(width: 8),
          Text(
            label,
            style: TextStyle(
              color: dark ? ReaderPalette.inverseText : ReaderPalette.ink,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _ReadMoreButton extends StatelessWidget {
  const _ReadMoreButton({required this.onTap, this.dark = false});

  final VoidCallback onTap;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final foreground = dark ? ReaderPalette.inverseText : ReaderPalette.primary;
    final background = dark
        ? Colors.white.withValues(alpha: 0.06)
        : ReaderPalette.surfaceMuted;
    final border = dark
        ? Colors.white.withValues(alpha: 0.08)
        : ReaderPalette.border;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'Read more',
              style: TextStyle(
                color: foreground,
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
            const SizedBox(width: 4),
            Icon(
              Icons.arrow_forward_ios_rounded,
              size: 12,
              color: foreground,
            ),
          ],
        ),
      ),
    );
  }
}

class _BoughtArticleCard extends StatelessWidget {
  const _BoughtArticleCard({
    required this.item,
    required this.onOpen,
    required this.onShare,
  });

  final _BoughtArticleItem item;
  final VoidCallback onOpen;
  final VoidCallback onShare;

  @override
  Widget build(BuildContext context) {
    final article = item.article;

    return InkWell(
      onTap: onOpen,
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: BoxDecoration(
          color: ReaderPalette.surface,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: ReaderPalette.border),
          boxShadow: ReaderPalette.softShadow,
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: SizedBox(
                  width: 108,
                  height: 116,
                  child: article.imageUrl != null
                      ? Image.network(
                          article.imageUrl!,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) =>
                              _ArticleImageFallback(
                                title: article.title,
                                height: 116,
                              ),
                        )
                      : _ArticleImageFallback(
                          title: article.title,
                          height: 116,
                        ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _MiniBadge(label: article.category),
                        _MiniBadge(
                          label: item.isActive ? 'Active' : 'Expired',
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Text(
                      article.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        height: 1.25,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      article.authorName,
                      style: const TextStyle(
                        color: ReaderPalette.inkMuted,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Bought with ${formatCoins(item.creditsSpent)} • ${_formatBoughtStatus(item)}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: ReaderPalette.inkMuted,
                        height: 1.45,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(child: _ReadMoreButton(onTap: onOpen)),
                        const SizedBox(width: 10),
                        _ArticleShareButton(onPressed: onShare),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ArticleImageFallback extends StatelessWidget {
  const _ArticleImageFallback({required this.title, this.height = 160});

  final String title;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(18),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            ReaderPalette.secondary,
            ReaderPalette.surfaceMuted,
            ReaderPalette.primarySoft,
          ],
        ),
      ),
      alignment: Alignment.bottomLeft,
      padding: const EdgeInsets.all(18),
      child: Text(
        title,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: ReaderPalette.primary,
          fontSize: 18,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({
    required this.message,
    required this.onRetry,
    required this.onLogout,
  });

  final String message;
  final Future<void> Function() onRetry;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.wifi_off_rounded,
                  size: 42,
                  color: ReaderPalette.primary,
                ),
                const SizedBox(height: 16),
                Text(
                  'Unable to load the reader dashboard',
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 10),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: ReaderPalette.inkMuted,
                    height: 1.6,
                  ),
                ),
                const SizedBox(height: 18),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () => onRetry(),
                    child: const Text('Retry'),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () => onLogout(),
                    child: const Text('Back to Login'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PostLoginTopUpDialog extends StatelessWidget {
  const _PostLoginTopUpDialog({required this.bundle});

  final DashboardBundle bundle;

  @override
  Widget build(BuildContext context) {
    final lockedArticles = bundle.articles
        .where((article) => !article.isUnlocked)
        .toList();
    final cheapestLockedPrice = lockedArticles.isEmpty
        ? null
        : lockedArticles
              .map((article) => article.price)
              .reduce((left, right) => left < right ? left : right);
    final balance = bundle.wallet.walletBalance;
    final needsTopUp =
        cheapestLockedPrice != null && balance < cheapestLockedPrice;

    return Dialog(
      backgroundColor: Colors.transparent,
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: ReaderPalette.surface,
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: ReaderPalette.border),
          boxShadow: const [
            BoxShadow(
              color: Color(0x14003973),
              blurRadius: 24,
              offset: Offset(0, 14),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(18),
                gradient: ReaderPalette.navGradient,
              ),
              child: const Icon(
                Icons.account_balance_wallet_rounded,
                color: ReaderPalette.inverseText,
                size: 28,
              ),
            ),
            const SizedBox(height: 18),
            Text(
              needsTopUp
                  ? 'Recharge wallet to unlock full articles'
                  : 'Top up your wallet',
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            Text(
              needsTopUp
                  ? 'Your current wallet balance is low for premium reading. Recharge now to open full articles without interruption.'
                  : 'Recharge your wallet to stay ready for premium stories and full-article access after login.',
              style: const TextStyle(
                color: ReaderPalette.inkMuted,
                height: 1.6,
              ),
            ),
            const SizedBox(height: 18),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                _PromptStatChip(
                  icon: Icons.account_balance_wallet_outlined,
                  label: 'Balance ${formatCoins(balance)}',
                ),
                if (cheapestLockedPrice != null)
                  _PromptStatChip(
                    icon: Icons.lock_open_rounded,
                    label: 'Unlocks from ${formatCoins(cheapestLockedPrice)}',
                  ),
                _PromptStatChip(
                  icon: Icons.menu_book_rounded,
                  label: '${lockedArticles.length} premium articles',
                ),
              ],
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () {
                  Navigator.of(context).pop(_TopUpPromptAction.recharge);
                },
                child: const Text('Recharge Wallet'),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () {
                  Navigator.of(context).pop(_TopUpPromptAction.later);
                },
                child: const Text('Maybe Later'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PromptStatChip extends StatelessWidget {
  const _PromptStatChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        color: ReaderPalette.surfaceMuted,
        border: Border.all(color: ReaderPalette.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: ReaderPalette.primary),
          const SizedBox(width: 8),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

String initialsFor(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .toList(growable: false);

  if (parts.isEmpty) {
    return 'R';
  }

  if (parts.length == 1) {
    return parts.first.substring(0, 1).toUpperCase();
  }

  return '${parts.first.substring(0, 1)}${parts.last.substring(0, 1)}'
      .toUpperCase();
}

String capitalize(String value) {
  if (value.isEmpty) {
    return value;
  }

  return value[0].toUpperCase() + value.substring(1);
}

List<_BoughtArticleItem> _buildBoughtArticles(DashboardBundle bundle) {
  final articleBySlug = {
    for (final article in bundle.articles) article.slug: article,
  };
  final entries = <_BoughtArticleItem>[];
  final seenSlugs = <String>{};

  for (final unlock in bundle.unlocks) {
    final article =
        articleBySlug[unlock.articleSlug] ??
        ArticleSummary(
          id: unlock.id,
          category: 'Premium',
          title: unlock.articleTitle,
          slug: unlock.articleSlug,
          imageUrl: unlock.articleImageUrl,
          previewText: 'Open your bought article from this saved reader library.',
          price: unlock.creditsSpent,
          accessDurationHours: null,
          viewCount: 0,
          unlockCount: 0,
          ratingAverage: 0,
          ratingCount: 0,
          authorName: unlock.authorName,
          isUnlocked: true,
        );

    entries.add(
      _BoughtArticleItem(
        article: article,
        creditsSpent: unlock.creditsSpent,
        unlockedAt: unlock.unlockedAt,
        expiresAt: unlock.expiresAt,
        isActive: unlock.isActive,
      ),
    );
    seenSlugs.add(article.slug);
  }

  for (final article in bundle.articles.where((article) => article.isUnlocked)) {
    if (seenSlugs.contains(article.slug)) {
      continue;
    }

    entries.add(
      _BoughtArticleItem(
        article: article,
        creditsSpent: article.price,
        unlockedAt: null,
        expiresAt: null,
        isActive: true,
      ),
    );
  }

  entries.sort((left, right) {
    final active = (right.isActive ? 1 : 0).compareTo(left.isActive ? 1 : 0);
    if (active != 0) {
      return active;
    }

    final rightTime = right.unlockedAt?.millisecondsSinceEpoch ?? 0;
    final leftTime = left.unlockedAt?.millisecondsSinceEpoch ?? 0;
    final unlockedAt = rightTime.compareTo(leftTime);
    if (unlockedAt != 0) {
      return unlockedAt;
    }

    return left.article.title.compareTo(right.article.title);
  });

  return entries;
}

String _formatBoughtStatus(_BoughtArticleItem item) {
  if (item.expiresAt != null) {
    return '${item.isActive ? 'Active until' : 'Expired on'} ${formatDate(item.expiresAt!)}';
  }

  if (item.unlockedAt != null) {
    return 'Bought on ${formatDate(item.unlockedAt!)}';
  }

  return item.isActive ? 'Access active' : 'Access ended';
}

String formatRating(double value) {
  return value.toStringAsFixed(1);
}

String formatCoins(int value) {
  return '$value coins';
}

String formatRupees(num value) {
  if (value is int || value == value.roundToDouble()) {
    return 'Rs ${value.toInt()}';
  }

  return 'Rs ${value.toStringAsFixed(2)}';
}

String formatCoinDelta(int value, {required bool isPositive}) {
  return '${isPositive ? '+' : '-'}${formatCoins(value)}';
}

String formatDate(DateTime value) {
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

String transactionTitle(WalletTransaction transaction) {
  switch (transaction.source) {
    case 'credit_purchase':
      return 'Added money to wallet';
    case 'article_unlock':
      return 'Unlocked premium article';
    case 'article_sale':
      return 'Article earnings received';
    case 'withdrawal_request':
      return 'Withdrawal requested';
    case 'withdrawal_reversal':
      return 'Withdrawal restored';
    default:
      return capitalize(transaction.source.replaceAll('_', ' '));
  }
}
