import 'package:flutter/material.dart';

import 'reader_palette.dart';

class ReaderBottomBar extends StatelessWidget {
  const ReaderBottomBar({
    super.key,
    required this.currentIndex,
    required this.onChanged,
  });

  final int currentIndex;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final items = <_BottomBarItem>[
      const _BottomBarItem(
        icon: Icons.explore_outlined,
        activeIcon: Icons.explore,
        label: 'Home',
      ),
      const _BottomBarItem(
        icon: Icons.search_rounded,
        activeIcon: Icons.search,
        label: 'Search',
      ),
      const _BottomBarItem(
        icon: Icons.bookmark_border_rounded,
        activeIcon: Icons.bookmark_rounded,
        label: 'Your Articles',
      ),
      const _BottomBarItem(
        icon: Icons.account_balance_wallet_outlined,
        activeIcon: Icons.account_balance_wallet,
        label: 'Wallet',
      ),
      const _BottomBarItem(
        icon: Icons.person_outline_rounded,
        activeIcon: Icons.person_rounded,
        label: 'Profile',
      ),
    ];

    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          color: ReaderPalette.surface,
          borderRadius: BorderRadius.circular(30),
          border: Border.all(color: ReaderPalette.border),
          boxShadow: ReaderPalette.softShadow,
        ),
        child: Row(
          children: List.generate(items.length, (index) {
            final item = items[index];
            final selected = index == currentIndex;

            return Expanded(
              child: GestureDetector(
                onTap: () => onChanged(index),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(22),
                    color: selected
                        ? ReaderPalette.surfaceMuted
                        : Colors.transparent,
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          gradient: selected ? ReaderPalette.navGradient : null,
                          color: selected ? null : ReaderPalette.surface,
                          border: Border.all(
                            color: selected
                                ? Colors.transparent
                                : ReaderPalette.border,
                          ),
                        ),
                        child: Icon(
                          selected ? item.activeIcon : item.icon,
                          color: selected
                              ? ReaderPalette.inverseText
                              : ReaderPalette.inkMuted,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        item.label,
                        style: TextStyle(
                          color: selected
                              ? ReaderPalette.primary
                              : ReaderPalette.inkMuted,
                          fontWeight: selected
                              ? FontWeight.w700
                              : FontWeight.w500,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _BottomBarItem {
  const _BottomBarItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
}
