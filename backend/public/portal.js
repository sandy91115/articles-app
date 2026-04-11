(function () {
    const state = {
        token: localStorage.getItem('cmp_token') || null,
        user: readStoredUser(),
        adminDirectoryUsers: [],
        articleImagePreviewUrl: null,
        pendingLoginPassword: '',
    };

    const elements = {
        appScreen: document.getElementById('appScreen'),
        articleModal: document.getElementById('articleModal'),
        articleModalContent: document.getElementById('articleModalContent'),
        authMessage: document.getElementById('authMessage'),
        authScreen: document.getElementById('authScreen'),
        authTabVerify: document.getElementById('authTabVerify'),
        authSwitchers: Array.from(document.querySelectorAll('[data-auth-switch]')),
        authTabs: Array.from(document.querySelectorAll('[data-auth-view]')),
        authTabsPanel: document.getElementById('authTabsPanel'),
        closeModalButton: document.getElementById('closeModalButton'),
        dashboardContent: document.getElementById('dashboardContent'),
        emailInput: document.getElementById('emailInput'),
        loginButton: document.getElementById('loginButton'),
        loginForm: document.getElementById('loginForm'),
        loginView: document.getElementById('loginView'),
        logoutButton: document.getElementById('logoutButton'),
        otpCodeInput: document.getElementById('otpCodeInput'),
        passwordInput: document.getElementById('passwordInput'),
        refreshButton: document.getElementById('refreshButton'),
        resendOtpButton: document.getElementById('resendOtpButton'),
        sidebarHint: document.getElementById('sidebarHint'),
        sidebarMetricLabel: document.getElementById('sidebarMetricLabel'),
        sidebarName: document.getElementById('sidebarName'),
        sidebarRole: document.getElementById('sidebarRole'),
        sidebarWallet: document.getElementById('sidebarWallet'),
        signupButton: document.getElementById('signupButton'),
        signupEmailInput: document.getElementById('signupEmailInput'),
        signupForm: document.getElementById('signupForm'),
        signupNameInput: document.getElementById('signupNameInput'),
        signupPasswordConfirmationInput: document.getElementById('signupPasswordConfirmationInput'),
        signupPasswordInput: document.getElementById('signupPasswordInput'),
        signupPhoneInput: document.getElementById('signupPhoneInput'),
        signupView: document.getElementById('signupView'),
        toast: document.getElementById('toast'),
        verifyButton: document.getElementById('verifyButton'),
        verifyEmailInput: document.getElementById('verifyEmailInput'),
        verifyOtpForm: document.getElementById('verifyOtpForm'),
        verifyView: document.getElementById('verifyView'),
        workspaceEyebrow: document.getElementById('workspaceEyebrow'),
        workspaceRoleBadge: document.getElementById('workspaceRoleBadge'),
        workspaceSubtitle: document.getElementById('workspaceSubtitle'),
        workspaceTitle: document.getElementById('workspaceTitle'),
    };

    const missingElements = Object.entries(elements)
        .filter(([, value]) => value === null)
        .map(([key]) => key);

    if (missingElements.length) {
        console.error('Portal failed to initialize. Missing elements:', missingElements.join(', '));
        return;
    }

    let toastTimer = null;

    bindEvents();
    restoreSession();

    function bindEvents() {
        elements.authTabs.forEach((button) => {
            button.addEventListener('click', () => {
                switchAuthView(button.dataset.authView);
            });
        });

        elements.authSwitchers.forEach((button) => {
            button.addEventListener('click', () => {
                switchAuthView(button.dataset.authSwitch);
            });
        });

        elements.loginForm.addEventListener('submit', handleLoginSubmit);
        elements.signupForm.addEventListener('submit', handleSignupSubmit);
        elements.verifyOtpForm.addEventListener('submit', handleVerifyOtpSubmit);
        elements.resendOtpButton.addEventListener('click', handleResendOtp);
        elements.refreshButton.addEventListener('click', renderDashboard);
        elements.logoutButton.addEventListener('click', handleLogout);
        elements.closeModalButton.addEventListener('click', closeArticleModal);
        elements.articleModal.addEventListener('click', (event) => {
            if (event.target === elements.articleModal) {
                closeArticleModal();
            }
        });

        document.querySelectorAll('[data-demo-login]').forEach((button) => {
            button.addEventListener('click', async () => {
                const [email, password] = button.dataset.demoLogin.split('|');
                switchAuthView('login');
                elements.emailInput.value = email;
                elements.passwordInput.value = password;
                await performLogin(email, password);
            });
        });

        elements.dashboardContent.addEventListener('click', handleDashboardClick);
        elements.dashboardContent.addEventListener('input', handleDashboardInput);
        elements.dashboardContent.addEventListener('change', handleDashboardChange);
        elements.dashboardContent.addEventListener('submit', handleDashboardSubmit);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeArticleModal();
            }
        });
    }

    async function restoreSession() {
        if (!state.token) {
            showAuth();
            return;
        }

        try {
            const response = await api('/api/auth/me');
            state.user = response.user;
            persistSession();
            showApp();
            await renderDashboard();
        } catch (_error) {
            clearSession();
            showAuth();
            setAuthMessage('Session expired. Please sign in again.', 'error');
        }
    }

    async function handleLoginSubmit(event) {
        event.preventDefault();
        await performLogin(elements.emailInput.value.trim(), elements.passwordInput.value);
    }

    async function handleSignupSubmit(event) {
        event.preventDefault();

        const name = elements.signupNameInput.value.trim();
        const email = elements.signupEmailInput.value.trim();
        const phone = elements.signupPhoneInput.value.trim();
        const password = elements.signupPasswordInput.value;
        const passwordConfirmation = elements.signupPasswordConfirmationInput.value;

        if (!name || !email || !password || !passwordConfirmation) {
            setAuthMessage('Name, email, and password fields are required for author signup.', 'error');
            return;
        }

        if (password !== passwordConfirmation) {
            setAuthMessage('Password confirmation does not match.', 'error');
            return;
        }

        setButtonBusy(elements.signupButton, true, 'Creating Account...');
        setAuthMessage('', 'info');

        try {
            const response = await api(
                '/api/auth/register',
                {
                    method: 'POST',
                    body: {
                        name,
                        email,
                        phone: phone || null,
                        password,
                        password_confirmation: passwordConfirmation,
                        role: 'author',
                    },
                },
                false,
            );

            state.pendingLoginPassword = password;
            elements.signupForm.reset();
            prepareVerificationFlow(email, password);
            switchAuthView('verify');
            setAuthMessage(buildOtpMessage(response.message, response.debug_code), 'success');
        } catch (error) {
            setAuthMessage(buildOtpMessage(error.message, error.payload?.debug_code), 'error');
        } finally {
            setButtonBusy(elements.signupButton, false, 'Create Author Account');
        }
    }

    async function handleVerifyOtpSubmit(event) {
        event.preventDefault();

        const email = elements.verifyEmailInput.value.trim();
        const code = elements.otpCodeInput.value.trim();

        if (!email || !code) {
            setAuthMessage('Email and the 6-digit OTP are required.', 'error');
            return;
        }

        setButtonBusy(elements.verifyButton, true, 'Verifying...');
        setAuthMessage('', 'info');

        try {
            const response = await api(
                '/api/auth/verify-otp',
                {
                    method: 'POST',
                    body: {
                        email,
                        code,
                        device_name: 'web-portal-verification',
                        issue_token: false,
                    },
                },
                false,
            );

            elements.verifyOtpForm.reset();
            elements.verifyEmailInput.value = email;
            elements.emailInput.value = email;

            if (state.pendingLoginPassword) {
                elements.passwordInput.value = state.pendingLoginPassword;
            }

            switchAuthView('login');
            setAuthMessage(response.message + ' Please sign in to open your dashboard.', 'success');
            showToast('Author account verified. You can sign in now.', 'success');
        } catch (error) {
            setAuthMessage(buildOtpMessage(error.message, error.payload?.debug_code), 'error');
        } finally {
            setButtonBusy(elements.verifyButton, false, 'Verify Email');
        }
    }

    async function handleResendOtp() {
        const email = elements.verifyEmailInput.value.trim() || elements.signupEmailInput.value.trim() || elements.emailInput.value.trim();

        if (!email) {
            setAuthMessage('Enter your email first so we know where to resend the OTP.', 'error');
            return;
        }

        setButtonBusy(elements.resendOtpButton, true, 'Sending OTP...');
        setAuthMessage('', 'info');

        try {
            const response = await api(
                '/api/auth/resend-otp',
                {
                    method: 'POST',
                    body: { email },
                },
                false,
            );

            elements.verifyEmailInput.value = email;
            switchAuthView('verify');
            setAuthMessage(buildOtpMessage(response.message, response.debug_code), 'success');
        } catch (error) {
            setAuthMessage(buildOtpMessage(error.message, error.payload?.debug_code), 'error');
        } finally {
            setButtonBusy(elements.resendOtpButton, false, 'Resend OTP');
        }
    }

    async function performLogin(email, password) {
        if (!email || !password) {
            setAuthMessage('Email and password are required.', 'error');
            return;
        }

        setButtonBusy(elements.loginButton, true, 'Signing In...');
        setAuthMessage('', 'info');

        try {
            const response = await api(
                '/api/auth/login',
                {
                    method: 'POST',
                    body: {
                        email,
                        password,
                        device_name: 'web-portal',
                    },
                },
                false,
            );

            state.token = response.token;
            state.user = response.user;
            state.pendingLoginPassword = '';
            persistSession();
            showApp();
            showToast(`Welcome back, ${response.user.name}.`, 'success');
            await renderDashboard();
        } catch (error) {
            if (error.status === 403) {
                prepareVerificationFlow(email, password);
                switchAuthView('verify');
            }

            setAuthMessage(buildOtpMessage(error.message, error.payload?.debug_code), 'error');
        } finally {
            setButtonBusy(elements.loginButton, false, 'Open Dashboard');
        }
    }

    async function handleLogout() {
        try {
            if (state.token) {
                await api('/api/auth/logout', { method: 'POST' });
            }
        } catch (_error) {
            // Ignore logout errors and clear local state.
        }

        clearSession();
        closeArticleModal();
        showAuth();
        showToast('You have been logged out.', 'success');
    }

    async function renderDashboard() {
        if (!state.user) {
            showAuth();
            return;
        }

        elements.appScreen.dataset.role = state.user.role || 'reader';
        elements.refreshButton.classList.remove('hidden');
        elements.workspaceRoleBadge.textContent = prettyRole(state.user.role);
        elements.sidebarName.textContent = state.user.name;
        elements.sidebarRole.textContent = prettyRole(state.user.role);
        revokeArticleImagePreview();
        elements.dashboardContent.innerHTML = loadingMarkup();
        state.adminDirectoryUsers = [];

        if (state.user.role === 'admin') {
            await renderAdminDashboardV2();
            return;
        }

        if (state.user.role === 'author') {
            await renderAuthorDashboard();
            return;
        }

        await renderReaderDashboard();
    }

    async function renderAdminDashboard() {
        try {
            const [dashboard, pendingArticles, pendingWithdrawals] = await Promise.all([
                api('/api/admin/dashboard'),
                api('/api/admin/articles/pending'),
                api('/api/admin/withdrawals/pending'),
            ]);

            const summary = dashboard.summary;
            const authors = dashboard.authors || [];
            const users = dashboard.users || [];
            const openQueueCount = pendingArticles.articles.length + pendingWithdrawals.withdrawals.length;
            const leadArticle = dashboard.article_performance[0] || null;
            const leadAuthor = authors[0] || null;

            updateShell({
                eyebrow: 'Platform Ops',
                title: 'Systematic Admin Dashboard',
                subtitle: 'Organized layout: metrics | queues | leaderboards | insights.',
                sidebarMetricLabel: 'Open Queue Count',
                sidebarMetricValue: openQueueCount,
                sidebarMetricFormatter: formatCount,
                hint: `${openQueueCount} pending actions across finance/moderation queues.`,
            });

            elements.dashboardContent.innerHTML = `
                <div class="admin-systematic-grid">
                    <!-- Header Metrics Row -->
                    <section class="surface section-block admin-header-row">
                        <div class="stat-card">
                            <span>Total Users</span>
                            <strong>${formatCount(summary.total_users)}</strong>
                            <p>${formatCount(summary.total_readers)} readers + ${formatCount(summary.total_authors)} authors</p>
                        </div>
                        <div class="stat-card">
                            <span>Live Articles</span>
                            <strong>${formatCount(summary.live_articles)}</strong>
                            <p>${formatCount(summary.pending_articles_count)} pending approval</p>
                        </div>
                        <div class="stat-card">
                            <span>Total Revenue</span>
                            <strong>${formatRupees(summary.total_revenue_rupees)}</strong>
                            <p>${formatCount(summary.total_credits_sold)} credits sold</p>
                        </div>
                        <div class="stat-card">
                            <span>Pending Payouts</span>
                            <strong>${formatCredits(summary.pending_withdrawals_amount)}</strong>
                            <p>${formatCount(summary.pending_withdrawals_count)} requests</p>
                        </div>
                    </section>

                    <!-- Main Layout: Queues Left, Leaderboards Right -->
                    <section class="surface section-block admin-main-layout">
                        <div class="admin-left-col">
                            <!-- Pending Withdrawals Table -->
                            <div class="section-head">
                                <h3>Finance Queue: Pending Withdrawals</h3>
                                <span class="badge">${formatCount(pendingWithdrawals.withdrawals.length)} items</span>
                            </div>
                            <div class="queue-table-container">
                                <table class="queue-table">
                                    <thead>
                                        <tr>
                                            <th>Author</th>
                                            <th>Amount</th>
                                            <th>Requested</th>
                                            <th>Reference</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${pendingWithdrawals.withdrawals.map(w => `
                                            <tr>
                                                <td>${escapeHtml(w.author?.name || 'Unknown')}</td>
                                                <td>${formatCredits(w.amount)}</td>
                                                <td>${formatDate(w.created_at)}</td>
                                                <td>${escapeHtml(w.reference_id)}</td>
                                                <td>
                                                    <div class="table-actions">
                                                        <button class="action-button approve compact-button" data-action="approve-withdrawal" data-withdrawal-id="${w.id}">Approve</button>
                                                        <button class="action-button reject compact-button" data-action="reject-withdrawal" data-withdrawal-id="${w.id}">Reject</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `).join('') || '<tr><td colspan="5" class="empty-state">No pending withdrawals</td></tr>'}
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pending Articles Table -->
                            <div class="section-head">
                                <h3>Publishing Queue: Pending Articles</h3>
                                <span class="badge">${formatCount(pendingArticles.articles.length)} items</span>
                            </div>
                            <div class="queue-table-container">
                                <table class="queue-table">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Price</th>
                                            <th>Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${pendingArticles.articles.map(a => `
                                            <tr>
                                                <td>${escapeHtml(a.title)}</td>
                                                <td>${escapeHtml(a.author?.name || 'Unknown')}</td>
                                                <td>${formatCredits(a.price)}</td>
                                                <td>${formatDate(a.updated_at)}</td>
                                                <td>
                                                    <div class="table-actions">
                                                        <button class="action-button approve compact-button" data-action="approve-article" data-article-id="${a.id}">Approve</button>
                                                        <button class="action-button reject compact-button" data-action="reject-article" data-article-id="${a.id}">Reject</button>
                                                    </div>
                                                </td>
                                            </tr>
                                        `).join('') || '<tr><td colspan="5" class="empty-state">No pending articles</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="admin-right-col">
                            <!-- Top Authors Leaderboard -->
                            <div class="section-head">
                                <h3>Top Authors Leaderboard</h3>
                                <span class="badge">${formatCount(authors.length)} ranked</span>
                            </div>
                            <div class="queue-table-container">
                                <table class="queue-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Author</th>
                                            <th>Articles</th>
                                            <th>Unlocks</th>
                                            <th>Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${authors.slice(0,10).map((author, i) => `
                                            <tr>
                                                <td>#${i+1}</td>
                                                <td>${escapeHtml(author.name)}</td>
                                                <td>${formatCount(author.stats?.published_articles_count || 0)}</td>
                                                <td>${formatCount(author.stats?.total_unlocks || 0)}</td>
                                                <td>${formatCredits(author.stats?.realized_earnings || 0)}</td>
                                            </tr>
                                        `).join('') || '<tr><td colspan="5" class="empty-state">No data</td></tr>'}
                                    </tbody>
                                </table>
                            </div>

                            <!-- Top Articles Leaderboard -->
                            <div class="section-head">
                                <h3>Top Articles Leaderboard</h3>
                                <span class="badge">Top 10 by unlocks</span>
                            </div>
                            <div class="queue-table-container">
                                <table class="queue-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Author</th>
                                            <th>Unlocks</th>
                                            <th>Views</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${dashboard.article_performance?.slice(0,10).map((art, i) => `
                                            <tr>
                                                <td>#${i+1}</td>
                                                <td>${escapeHtml(art.title)}</td>
                                                <td>${escapeHtml(art.author?.name)}</td>
                                                <td>${formatCount(art.unlock_count)}</td>
                                                <td>${formatCount(art.view_count)}</td>
                                            </tr>
                                        `).join('') || '<tr><td colspan="5" class="empty-state">No data</td></tr>'}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- Full-width: User Directory -->
                    <section class="admin-full-section surface section-block">
                        <div class="section-head">
                            <h3>User Directory</h3>
                            <span class="badge">${formatCount(users.length)} total</span>
                        </div>
                        <div class="queue-table-container">
                            <div class="queue-table-toolbar">
                                <input type="text" class="table-search" placeholder="Search users by name or email...">
                                <button class="export-btn" type="button">Export CSV</button>
                            </div>
                            <table class="queue-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Email</th>
                                        <th>Wallet</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${users.slice(0,20).map(u => `
                                        <tr>
                                            <td>${escapeHtml(u.name)}</td>
                                            <td>${prettyRole(u.role)}</td>
                                            <td>${escapeHtml(u.email)}</td>
                                            <td>${formatCredits(u.wallet_balance)}</td>
                                            <td>${formatDate(u.created_at)}</td>
                                        </tr>
                                    `).join('') || '<tr><td colspan="5" class="empty-state">No users</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            `;
        } catch (error) {
            renderError(error);
        }
    }

    async function renderAdminDashboardV2() {
        try {
            const [dashboard, pendingArticles, pendingWithdrawals] = await Promise.all([
                api('/api/admin/dashboard'),
                api('/api/admin/articles/pending'),
                api('/api/admin/withdrawals/pending'),
            ]);

            const summary = dashboard.summary;
            const authors = dashboard.authors || [];
            const users = dashboard.users || [];
            const topAuthors = authors.slice(0, 5);
            const topArticles = (dashboard.article_performance || []).slice(0, 4);
            const openQueueCount = pendingArticles.articles.length + pendingWithdrawals.withdrawals.length;
            const leadArticle = topArticles[0] || null;
            const leadAuthor = topAuthors[0] || null;
            const activityItems = buildAdminActivityFeed(users, pendingArticles.articles, pendingWithdrawals.withdrawals).slice(0, 4);

            state.adminDirectoryUsers = users;

            updateShell({
                eyebrow: 'Platform Ops',
                title: 'Operational Overview',
                subtitle: 'Queues first, insights second, and full records below.',
                sidebarMetricLabel: 'Open Queue Count',
                sidebarMetricValue: openQueueCount,
                sidebarMetricFormatter: formatCount,
                hint: `${openQueueCount} pending actions across finance/moderation queues.`,
            });

            elements.dashboardContent.innerHTML = `
                <div class="admin-systematic-grid admin-dashboard-v2">
                    <section class="surface section-block admin-overview-hero">
                        <div class="admin-hero-copy">
                            <p class="eyebrow accent">Overview</p>
                            <h3>Platform health, queues, and revenue at a glance</h3>
                            <p class="admin-section-copy">
                                Primary actions stay left, supporting insights stay right, and the full directory sits below
                                for cleaner scanning.
                            </p>
                            <div class="metric-chip-list">
                                ${metricChip('Active Authors', formatCount(summary.active_authors), summary.active_authors ? 'good' : 'neutral')}
                                ${metricChip('Engaged Readers', formatCount(summary.engaged_readers), summary.engaged_readers ? 'good' : 'neutral')}
                                ${metricChip('Platform Rating', formatRating(summary.platform_rating_average, summary.platform_rating_votes), summary.platform_rating_votes ? 'good' : 'neutral')}
                            </div>
                        </div>

                        <div class="admin-hero-actions">
                            <div class="admin-hero-badges">
                                <span class="badge">All-time snapshot</span>
                                <span class="badge">${openQueueCount ? `${formatCount(openQueueCount)} attention items` : 'All queues clear'}</span>
                            </div>
                            <div class="action-group">
                                <a class="ghost-button admin-link-button" href="#adminQueues">Review Queues</a>
                                <a class="ghost-button admin-link-button" href="#adminInsights">Open Insights</a>
                                <a class="ghost-button admin-link-button" href="#adminDirectory">Manage Users</a>
                            </div>
                        </div>
                    </section>

                    <section class="admin-kpi-row">
                        ${adminKpiCard('Total Users', formatCount(summary.total_users), `${formatCount(summary.total_readers)} readers + ${formatCount(summary.total_authors)} authors`)}
                        ${adminKpiCard('Live Articles', formatCount(summary.live_articles), `${formatCount(summary.pending_articles_count)} still waiting for approval`)}
                        ${adminKpiCard('Total Revenue', formatRupees(summary.total_revenue_rupees), `${formatCount(summary.total_credits_sold)} credits sold platform-wide`)}
                        ${adminKpiCard('Pending Payouts', formatCredits(summary.pending_withdrawals_amount), `${formatCount(summary.pending_withdrawals_count)} withdrawal requests open`)}
                        ${adminKpiCard('Unlock Activity', formatCount(summary.total_article_unlocks), `${formatCount(summary.total_article_views)} views across published content`)}
                    </section>

                    <section class="admin-main-grid">
                        <div id="adminQueues" class="admin-section-stack">
                            <section class="surface section-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Pending Actions</p>
                                        <h3>Review queues first</h3>
                                    </div>
                                    <span class="badge">${formatCount(openQueueCount)} open</span>
                                </div>
                                <div class="admin-queue-summary-grid">
                                    ${adminQueueOverviewCard('Finance Queue', formatCount(pendingWithdrawals.withdrawals.length), `${formatCredits(summary.pending_withdrawals_amount)} waiting for payout approval.`)}
                                    ${adminQueueOverviewCard('Content Queue', formatCount(pendingArticles.articles.length), `${formatCount(summary.pending_articles_count)} stories are waiting for editorial review.`)}
                                </div>
                            </section>

                            <section class="surface section-block admin-queue-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Finance</p>
                                        <h3>Pending Withdrawals</h3>
                                    </div>
                                    <span class="badge">${formatCount(pendingWithdrawals.withdrawals.length)} items</span>
                                </div>
                                <div class="queue-table-container">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Author</th>
                                                <th>Amount</th>
                                                <th>Requested</th>
                                                <th>Reference</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${pendingWithdrawals.withdrawals.map((withdrawal) => adminWithdrawalQueueRow(withdrawal)).join('') || '<tr><td colspan="5" class="empty-state">No pending withdrawals</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>

                        <div id="adminInsights" class="admin-insight-rail">
                            <section class="surface section-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Alerts</p>
                                        <h3>What needs attention</h3>
                                    </div>
                                    <span class="badge">Ops signals</span>
                                </div>
                                <div class="admin-alert-stack">
                                    ${openQueueCount ? adminSpotlightItem('Open Queues', `${formatCount(openQueueCount)} items`, 'Withdrawals and article approvals need attention.', 'warn') : adminSpotlightItem('Queue Status', 'All clear', 'No payout or article approvals are waiting right now.', 'good')}
                                    ${pendingWithdrawals.withdrawals.length ? adminSpotlightItem('Pending Payout Amount', formatCredits(summary.pending_withdrawals_amount), `${formatCount(summary.pending_withdrawals_count)} payout requests are awaiting review.`, 'warn') : adminSpotlightItem('Payout Flow', 'No blockers', 'The finance queue has no pending withdrawal requests.', 'good')}
                                    ${leadAuthor ? adminSpotlightItem('Top Earning Author', leadAuthor.name, `${formatCredits(leadAuthor.stats.realized_earnings)} earned across ${formatCount(leadAuthor.stats.published_articles_count)} published articles.`, 'good') : ''}
                                    ${leadArticle ? adminSpotlightItem('Best Performing Story', leadArticle.title, `${formatCount(leadArticle.unlock_count)} unlocks and ${formatCount(leadArticle.view_count)} views so far.`, 'neutral') : ''}
                                </div>
                            </section>

                            <section class="surface section-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Leaderboard</p>
                                        <h3>Top Authors</h3>
                                    </div>
                                    <span class="badge">${formatCount(topAuthors.length)} shown</span>
                                </div>
                                <div class="admin-leaderboard-stack">
                                    ${topAuthors.length ? topAuthors.map(authorLeaderboardCard).join('') : emptyState('No author leaderboard data is available yet.')}
                                </div>
                            </section>
                        </div>
                    </section>

                    <section class="admin-analytics-grid">
                        <div class="admin-section-stack">
                            <section class="surface section-block admin-queue-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Content</p>
                                        <h3>Pending Articles</h3>
                                    </div>
                                    <span class="badge">${formatCount(pendingArticles.articles.length)} items</span>
                                </div>
                                <div class="queue-table-container">
                                    <table class="queue-table">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Author</th>
                                                <th>Price</th>
                                                <th>Updated</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${pendingArticles.articles.map((article) => adminArticleQueueRow(article)).join('') || '<tr><td colspan="5" class="empty-state">No pending articles</td></tr>'}
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="surface section-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Platform Pulse</p>
                                        <h3>Business performance</h3>
                                    </div>
                                    <span class="badge">Key health</span>
                                </div>
                                <div class="admin-health-grid">
                                    ${adminSignalCard('Lifetime Views', formatCount(summary.total_article_views), 'All published article page views on the platform')}
                                    ${adminSignalCard('Lifetime Unlocks', formatCount(summary.total_article_unlocks), 'Completed paid unlocks from readers')}
                                    ${adminSignalCard('Author Earnings', formatCredits(summary.author_earnings), 'Credits routed to authors from premium reading')}
                                    ${adminSignalCard('Commission Earned', formatCredits(summary.commission_earned), 'Platform share retained from unlock activity')}
                                </div>
                            </section>
                        </div>

                        <div class="admin-section-stack">
                            <section class="surface section-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Content</p>
                                        <h3>Top Articles</h3>
                                    </div>
                                    <span class="badge">${formatCount(topArticles.length)} shown</span>
                                </div>
                                <div class="admin-leaderboard-stack">
                                    ${topArticles.length ? topArticles.map(articleLeaderboardCard).join('') : emptyState('No article performance data is available yet.')}
                                </div>
                            </section>

                            <section class="surface section-block">
                                <div class="section-head">
                                    <div>
                                        <p class="eyebrow">Recent Activity</p>
                                        <h3>Newest platform moments</h3>
                                    </div>
                                    <span class="badge">${formatCount(activityItems.length)} updates</span>
                                </div>
                                <div class="admin-activity-feed">
                                    ${activityItems.length ? activityItems.map(activityFeedItem).join('') : emptyState('No recent activity is available yet.')}
                                </div>
                            </section>
                        </div>
                    </section>

                    <section id="adminDirectory" class="surface section-block admin-directory-section">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">User Management</p>
                                <h3>User Directory</h3>
                            </div>
                            <span class="badge" data-directory-count>${formatCount(users.length)} users</span>
                        </div>
                        <div class="queue-table-container admin-directory-shell">
                            <div class="queue-table-toolbar">
                                <input class="table-search" type="search" placeholder="Search users by name, email, phone, or role..." data-directory-search>
                                <button class="export-btn" type="button" data-action="export-users">Export CSV</button>
                            </div>
                            <table class="queue-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Role</th>
                                        <th>Contact</th>
                                        <th>Wallet</th>
                                        <th>Joined</th>
                                        <th>Highlights</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${users.map(userDirectoryRow).join('')}
                                    <tr class="hidden" data-directory-empty>
                                        <td colspan="6" class="empty-state">No users match this search.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            `;

            filterAdminDirectory('');
        } catch (error) {
            renderError(error);
        }
    }

    async function renderAuthorDashboard() {
        try {
            const [wallet, articles, withdrawals] = await Promise.all([
                api('/api/wallet'),
                api('/api/articles/mine'),
                api('/api/withdrawals'),
            ]);

            const publishedCount = articles.articles.filter((article) => article.status === 'published').length;
            const withdrawalCount = withdrawals.withdrawals.length;

            updateShell({
                eyebrow: 'Author Studio',
                title: 'Write, price, and publish premium stories',
                subtitle: 'Create paid articles instantly and request manual withdrawals when you want to cash out.',
                sidebarMetricLabel: 'Wallet Balance',
                sidebarMetricValue: wallet.wallet_balance,
                hint: 'Your balance updates when readers unlock published articles.',
            });

            elements.dashboardContent.innerHTML = `
                <section class="summary-grid">
                    ${summaryCard('Wallet', formatCredits(wallet.wallet_balance), 'Available credits in your creator wallet')}
                    ${summaryCard('Published', String(publishedCount), 'Articles currently available to readers')}
                    ${summaryCard('Withdrawals', String(withdrawalCount), 'Manual payout requests sent for admin approval')}
                </section>

                <section class="split-layout">
                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">New Story</p>
                                <h3>Create Paid Article</h3>
                            </div>
                            <span class="badge">Author tools</span>
                        </div>

                        <form id="articleCreateForm" class="inline-form">
                            <label class="field">
                                <span>Title</span>
                                <input name="title" type="text" required>
                            </label>

                            <label class="field">
                                <span>Cover Image</span>
                                <input name="image" type="file" accept="image/png,image/jpeg,image/webp,image/gif" data-article-image-input>
                                <div class="helper-note">Upload JPG, PNG, WEBP, or GIF up to 4 MB. This image will be used as the article cover.</div>
                            </label>

                            <div class="image-upload-preview hidden" data-article-image-preview>
                                <div class="image-upload-copy">
                                    <span>Selected cover</span>
                                    <strong data-article-image-name>No file selected</strong>
                                </div>
                                <img src="" alt="Selected article cover" data-article-image-tag>
                            </div>

                            ${articleEditorField({
                                name: 'preview_text',
                                label: 'Preview Text',
                                placeholder: 'Write the teaser readers will see before they unlock the story...',
                                helper: 'This appears in previews, cards, and paywall moments.',
                                size: 'compact',
                            })}

                            ${articleEditorField({
                                name: 'content',
                                label: 'Full Content',
                                placeholder: 'Write the complete paid article here. Use paragraphs, bullet points, and quotes to keep it readable...',
                                helper: 'This is the full article unlocked readers will get access to.',
                                size: 'full',
                            })}

                            <div class="purchase-grid">
                                <label class="field">
                                    <span>Price (credits)</span>
                                    <input name="price" type="number" min="1" required>
                                </label>

                                <label class="field">
                                    <span>Access Duration (hours)</span>
                                    <input name="access_duration_hours" type="number" min="1" value="24">
                                </label>
                            </div>

                            <div class="purchase-grid">
                                <label class="field">
                                    <span>Commission Type</span>
                                    <select name="commission_type">
                                        <option value="percentage">Percentage</option>
                                        <option value="fixed">Fixed credits</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span>Commission Value</span>
                                    <input name="commission_value" type="number" min="0" value="10" required>
                                </label>
                            </div>

                            <div class="empty-state">
                                Articles now go live directly from the author panel. Only withdrawals still need admin approval.
                            </div>

                            <button class="primary-button" type="submit">Publish Article</button>
                        </form>
                    </div>

                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Credits</p>
                                <h3>Wallet Actions</h3>
                            </div>
                            <span class="badge">Manual payout flow</span>
                        </div>

                        <form id="purchaseCreditsForm" class="inline-form">
                            <label class="field">
                                <span>Create Credit Purchase Order</span>
                                <input name="credits" type="number" min="50" value="200" required>
                            </label>
                            <button class="ghost-button" type="submit">Create Razorpay Order</button>
                        </form>

                        <form id="withdrawalRequestForm" class="inline-form">
                            <label class="field">
                                <span>Withdrawal Amount (credits)</span>
                                <input name="amount" type="number" min="100" value="100" required>
                            </label>
                            <button class="primary-button" type="submit">Request Withdrawal</button>
                        </form>
                    </div>
                </section>

                <section class="split-layout">
                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">My Articles</p>
                                <h3>Live Publishing Status</h3>
                            </div>
                            <span class="badge">${articles.articles.length} total</span>
                        </div>
                        <div class="queue-list">
                            ${articles.articles.length
                                ? articles.articles.map(authorArticleCard).join('')
                                : emptyState('Create your first paid article from the form above.')}
                        </div>
                    </div>

                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Withdrawal Log</p>
                                <h3>Payout Requests</h3>
                            </div>
                            <span class="badge">${withdrawals.withdrawals.length} entries</span>
                        </div>
                        <div class="queue-list">
                            ${withdrawals.withdrawals.length
                                ? withdrawals.withdrawals.map(authorWithdrawalCard).join('')
                                : emptyState('No withdrawals requested yet.')}
                        </div>
                    </div>
                </section>
            `;

            initializeArticleComposer();
        } catch (error) {
            renderError(error);
        }
    }

    async function renderReaderDashboard() {
        try {
            const [wallet, articles, unlocks] = await Promise.all([
                api('/api/wallet'),
                api('/api/articles'),
                api('/api/unlocks'),
            ]);

            updateShell({
                eyebrow: 'Reader Lounge',
                title: 'Browse previews and unlock premium articles',
                subtitle: 'Your wallet, unlocked stories, and premium catalog live together here.',
                sidebarMetricLabel: 'Wallet Balance',
                sidebarMetricValue: wallet.wallet_balance,
                hint: 'Unlocks deduct credits from the wallet on the backend.',
            });

            elements.dashboardContent.innerHTML = `
                <section class="summary-grid">
                    ${summaryCard('Wallet', formatCredits(wallet.wallet_balance), 'Credits available to unlock content')}
                    ${summaryCard('Unlocked Articles', String(unlocks.unlocks.length), 'Stories already accessible in your account')}
                    ${summaryCard('Catalog Size', String(articles.articles.length), 'Published premium articles available now')}
                </section>

                <section class="split-layout">
                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Wallet</p>
                                <h3>Buy Credits</h3>
                            </div>
                            <span class="badge">Razorpay order creation</span>
                        </div>

                        <form id="purchaseCreditsForm" class="inline-form">
                            <label class="field">
                                <span>Credits</span>
                                <input name="credits" type="number" min="50" value="200" required>
                            </label>
                            <button class="primary-button" type="submit">Create Purchase Order</button>
                        </form>

                        <div class="empty-state">
                            This creates the payment order from Laravel. If Razorpay keys are not configured yet,
                            the portal will show the backend error directly.
                        </div>
                    </div>

                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Unlocked</p>
                                <h3>My Premium Library</h3>
                            </div>
                            <span class="badge">${unlocks.unlocks.length} active + past</span>
                        </div>
                        <div class="queue-list">
                            ${unlocks.unlocks.length
                                ? unlocks.unlocks.map(unlockCard).join('')
                                : emptyState('Unlock an article to see it appear here.')}
                        </div>
                    </div>
                </section>

                <section class="surface section-block">
                    <div class="section-head">
                        <div>
                            <p class="eyebrow">Catalog</p>
                            <h3>Premium Articles</h3>
                        </div>
                        <span class="badge">${articles.articles.length} available</span>
                    </div>
                    <div class="catalog-grid">
                        ${articles.articles.length
                            ? articles.articles.map(readerCatalogCard).join('')
                            : emptyState('No published articles are available yet.')}
                    </div>
                </section>
            `;
        } catch (error) {
            renderError(error);
        }
    }

    async function handleDashboardClick(event) {
        const trigger = event.target.closest('[data-action]');

        if (!trigger) {
            return;
        }

        const action = trigger.dataset.action;
        const originalText = trigger.dataset.originalText || trigger.textContent || 'Action';

        if (action === 'editor-command') {
            applyEditorCommand(trigger);
            return;
        }

        try {
            if (action === 'approve-article') {
                setButtonBusy(trigger, true, 'Approving...');
                await api(`/api/admin/articles/${trigger.dataset.articleId}/approve`, { method: 'POST', body: {} });
                showToast('Article approved and published.', 'success');
                await renderDashboard();
            }

            if (action === 'reject-article') {
                const reason = window.prompt('Reject reason');

                if (!reason) {
                    return;
                }

                setButtonBusy(trigger, true, 'Rejecting...');
                await api(`/api/admin/articles/${trigger.dataset.articleId}/reject`, {
                    method: 'POST',
                    body: { reason },
                });
                showToast('Article rejected.', 'success');
                await renderDashboard();
            }

            if (action === 'approve-withdrawal') {
                const adminNotes = window.prompt('Optional admin note', '') || '';
                setButtonBusy(trigger, true, 'Approving...');
                await api(`/api/admin/withdrawals/${trigger.dataset.withdrawalId}/approve`, {
                    method: 'POST',
                    body: { admin_notes: adminNotes },
                });
                showToast('Withdrawal approved.', 'success');
                await renderDashboard();
            }

            if (action === 'reject-withdrawal') {
                const adminNotes = window.prompt('Reason / note for rejection', '') || '';
                setButtonBusy(trigger, true, 'Rejecting...');
                await api(`/api/admin/withdrawals/${trigger.dataset.withdrawalId}/reject`, {
                    method: 'POST',
                    body: { admin_notes: adminNotes },
                });
                showToast('Withdrawal rejected and balance restored.', 'success');
                await renderDashboard();
            }

            if (action === 'export-users') {
                exportAdminUsers();
            }

            if (action === 'open-article') {
                await openArticle(trigger.dataset.slug, trigger.dataset.title);
            }

            if (action === 'unlock-article') {
                setButtonBusy(trigger, true, 'Unlocking...');
                await api(`/api/articles/${trigger.dataset.slug}/unlock`, {
                    method: 'POST',
                    body: {},
                });
                showToast('Article unlocked successfully.', 'success');
                await renderDashboard();
                await openArticle(trigger.dataset.slug, trigger.dataset.title);
            }
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            if (trigger instanceof HTMLButtonElement) {
                setButtonBusy(trigger, false, originalText);
            }
        }
    }

    function handleDashboardInput(event) {
        if (!(event.target instanceof HTMLInputElement) && !(event.target instanceof HTMLTextAreaElement)) {
            return;
        }

        if (event.target.matches('[data-directory-search]')) {
            filterAdminDirectory(event.target.value);
            return;
        }

        if (event.target.matches('[data-editor-input]')) {
            updateEditorCount(event.target);
        }
    }

    function handleDashboardChange(event) {
        if (!(event.target instanceof HTMLInputElement)) {
            return;
        }

        if (event.target.matches('[data-article-image-input]')) {
            syncArticleImagePreview(event.target);
        }
    }

    async function handleDashboardSubmit(event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        event.preventDefault();
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton?.dataset.originalText || submitButton?.textContent || 'Submit';
        const formData = new FormData(form);

        try {
            if (form.id === 'articleCreateForm') {
                setButtonBusy(submitButton, true, 'Saving...');
                const articleFormData = new FormData(form);
                const imageFile = articleFormData.get('image');

                if (imageFile instanceof File && !imageFile.name) {
                    articleFormData.delete('image');
                }

                await api('/api/articles', {
                    method: 'POST',
                    body: articleFormData,
                });
                form.reset();
                showToast('Article published successfully.', 'success');
                await renderDashboard();
            }

            if (form.id === 'withdrawalRequestForm') {
                setButtonBusy(submitButton, true, 'Submitting...');
                await api('/api/withdrawals', {
                    method: 'POST',
                    body: {
                        amount: Number(formData.get('amount') || 0),
                    },
                });
                showToast('Withdrawal request submitted.', 'success');
                await renderDashboard();
            }

            if (form.id === 'purchaseCreditsForm') {
                setButtonBusy(submitButton, true, 'Creating...');
                const order = await api('/api/wallet/purchase-orders', {
                    method: 'POST',
                    body: {
                        credits: Number(formData.get('credits') || 0),
                    },
                });

                showToast(
                    `Purchase order created: ${order.order.reference}. Hook this into Razorpay checkout next.`,
                    'success',
                );
            }
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            if (submitButton) {
                setButtonBusy(submitButton, false, originalText);
            }
        }
    }

    async function openArticle(slug, title) {
        try {
            const response = await api(`/api/articles/${slug}`);
            const article = response.article;

            elements.articleModalContent.innerHTML = `
                <div class="detail-card">
                    <p class="eyebrow accent">Article Detail</p>
                    <h3>${escapeHtml(article.title || title || 'Article')}</h3>
                    <div class="detail-meta">
                        <span>Price: ${formatCredits(article.price || 0)}</span>
                        <span>Status: ${prettyStatus(article.status)}</span>
                        <span>Views: ${article.view_count ?? 0}</span>
                        <span>Unlocks: ${article.unlock_count ?? 0}</span>
                        ${article.access_expires_at ? `<span>Access until: ${formatDate(article.access_expires_at)}</span>` : ''}
                    </div>
                    <p>${escapeHtml(article.preview_text || '')}</p>
                    ${article.content
                        ? `<div class="article-body">${escapeHtml(article.content)}</div>`
                        : `<div class="empty-state">Full content is still locked. Unlock the article to read it here.</div>`}
                    <div class="action-group">
                        ${article.is_unlocked
                            ? ''
                            : `<button class="primary-button" type="button" data-action="unlock-article" data-slug="${escapeHtml(slug)}" data-title="${escapeHtml(article.title || title || 'Article')}">Unlock for ${article.price} credits</button>`}
                    </div>
                </div>
            `;

            elements.articleModal.classList.remove('hidden');
        } catch (error) {
            showToast(error.message, 'error');
        }
    }

    function closeArticleModal() {
        elements.articleModal.classList.add('hidden');
        elements.articleModalContent.innerHTML = '';
    }

    async function api(path, options = {}, includeAuth = true) {
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };

        const fetchOptions = {
            method: options.method || 'GET',
            headers,
        };

        if (options.body !== undefined) {
            if (options.body instanceof FormData) {
                fetchOptions.body = options.body;
            } else {
                fetchOptions.body = JSON.stringify(options.body);
                fetchOptions.headers['Content-Type'] = 'application/json';
            }
        }

        if (includeAuth && state.token) {
            fetchOptions.headers.Authorization = `Bearer ${state.token}`;
        }

        const response = await fetch(path, fetchOptions);
        const contentType = response.headers.get('content-type') || '';
        const payload = contentType.includes('application/json')
            ? await response.json()
            : await response.text();

        if (!response.ok) {
            const error = new Error(extractMessage(payload) || `Request failed with status ${response.status}`);
            error.payload = payload;
            error.status = response.status;

            if (response.status === 401) {
                clearSession();
                showAuth();
            }

            throw error;
        }

        return payload;
    }

    function updateShell({
        eyebrow,
        title,
        subtitle,
        sidebarMetricLabel = 'Wallet Balance',
        sidebarMetricValue = 0,
        sidebarMetricFormatter = formatCredits,
        hint,
    }) {
        elements.workspaceEyebrow.textContent = eyebrow;
        elements.workspaceTitle.textContent = title;
        elements.workspaceSubtitle.textContent = subtitle;
        elements.sidebarMetricLabel.textContent = sidebarMetricLabel;
        elements.sidebarWallet.textContent = sidebarMetricFormatter(sidebarMetricValue || 0);
        elements.sidebarHint.textContent = hint;
    }

    function renderError(error) {
        elements.dashboardContent.innerHTML = emptyState(
            escapeHtml(error.message || 'Something went wrong while loading the dashboard.'),
        );
    }

    function showAuth() {
        revokeArticleImagePreview();
        elements.appScreen.classList.add('hidden');
        elements.authScreen.classList.remove('hidden');
        elements.refreshButton.classList.add('hidden');
        elements.appScreen.dataset.role = '';
        switchAuthView('login');
    }

    function showApp() {
        elements.authScreen.classList.add('hidden');
        elements.appScreen.classList.remove('hidden');
    }

    function persistSession() {
        localStorage.setItem('cmp_token', state.token || '');
        localStorage.setItem('cmp_user', JSON.stringify(state.user || null));
    }

    function clearSession() {
        revokeArticleImagePreview();
        state.token = null;
        state.user = null;
        state.adminDirectoryUsers = [];
        localStorage.removeItem('cmp_token');
        localStorage.removeItem('cmp_user');
    }

    function setAuthMessage(message, type) {
        if (!message) {
            elements.authMessage.className = 'message-box hidden';
            elements.authMessage.textContent = '';
            return;
        }

        elements.authMessage.className = `message-box ${type || 'info'}`;
        elements.authMessage.textContent = message;
    }

    function switchAuthView(view) {
        const normalizedView = view || 'login';
        const views = {
            login: elements.loginView,
            signup: elements.signupView,
            verify: elements.verifyView,
        };

        setVerifyStepVisibility(normalizedView === 'verify');

        Object.entries(views).forEach(([key, node]) => {
            node.classList.toggle('hidden', key !== normalizedView);
        });

        elements.authTabs.forEach((button) => {
            const isActive = button.dataset.authView === normalizedView;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', String(isActive));
            button.setAttribute('tabindex', isActive ? '0' : '-1');
        });
    }

    function setVerifyStepVisibility(visible) {
        elements.authTabVerify.classList.toggle('hidden', !visible);
        elements.authTabVerify.setAttribute('aria-hidden', String(!visible));
        elements.authTabsPanel.classList.toggle('with-verify', visible);
    }

    function prepareVerificationFlow(email, password = '') {
        elements.verifyEmailInput.value = email;
        elements.emailInput.value = email;
        elements.otpCodeInput.value = '';

        if (password) {
            state.pendingLoginPassword = password;
            elements.passwordInput.value = password;
        }
    }

    function buildOtpMessage(message, debugCode) {
        if (!debugCode) {
            return message;
        }

        return `${message} Debug OTP: ${debugCode}`;
    }

    function setButtonBusy(button, busy, label) {
        if (!button) {
            return;
        }

        if (!button.dataset.originalText) {
            button.dataset.originalText = button.textContent;
        }

        button.disabled = busy;
        button.textContent = busy ? label : button.dataset.originalText;
    }

    function showToast(message, type) {
        clearTimeout(toastTimer);
        elements.toast.textContent = message;
        elements.toast.className = `toast ${type || 'info'}`;
        elements.toast.classList.remove('hidden');

        toastTimer = window.setTimeout(() => {
            elements.toast.classList.add('hidden');
        }, 3600);
    }

    function extractMessage(payload) {
        if (!payload) {
            return '';
        }

        if (typeof payload === 'string') {
            return payload;
        }

        if (payload.message && typeof payload.message === 'string') {
            return payload.message;
        }

        if (payload.errors) {
            const firstKey = Object.keys(payload.errors)[0];

            if (firstKey && Array.isArray(payload.errors[firstKey])) {
                return payload.errors[firstKey][0];
            }
        }

        return '';
    }

    function summaryCard(label, value, description) {
        return `
            <article class="summary-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <p>${escapeHtml(description)}</p>
            </article>
        `;
    }

    function articleEditorField({ name, label, placeholder, helper, size = 'full' }) {
        return `
            <div class="field field-editor">
                <span>${escapeHtml(label)}</span>
                <div class="editor-shell">
                    <div class="editor-toolbar" role="toolbar" aria-label="${escapeHtml(label)} tools">
                        ${editorCommandButton(name, 'paragraph', 'Break')}
                        ${editorCommandButton(name, 'bullet', 'Bullets')}
                        ${editorCommandButton(name, 'quote', 'Quote')}
                        ${editorCommandButton(name, 'divider', 'Divider')}
                    </div>
                    <textarea
                        name="${escapeHtml(name)}"
                        class="editor-textarea editor-textarea-${escapeHtml(size)}"
                        placeholder="${escapeHtml(placeholder)}"
                        data-editor-input
                        data-editor-name="${escapeHtml(name)}"
                        required
                    ></textarea>
                    <div class="editor-footer">
                        <span>${escapeHtml(helper)}</span>
                        <strong data-editor-count="${escapeHtml(name)}">0 words</strong>
                    </div>
                </div>
            </div>
        `;
    }

    function editorCommandButton(target, command, label) {
        return `
            <button
                class="editor-tool"
                type="button"
                data-action="editor-command"
                data-editor-target="${escapeHtml(target)}"
                data-editor-command="${escapeHtml(command)}"
            >
                ${escapeHtml(label)}
            </button>
        `;
    }

    function adminSignalCard(label, value, description) {
        return `
            <article class="admin-signal-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <p>${escapeHtml(description)}</p>
            </article>
        `;
    }

    function adminSpotlightItem(label, value, description, tone = 'neutral') {
        return `
            <article class="admin-spotlight-item${tone !== 'neutral' ? ` tone-${tone}` : ''}">
                <div>
                    <span>${escapeHtml(label)}</span>
                    <strong>${escapeHtml(value)}</strong>
                </div>
                <p>${escapeHtml(description)}</p>
            </article>
        `;
    }

    function adminKpiCard(label, value, description) {
        return `
            <article class="stat-card admin-kpi-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <p>${escapeHtml(description)}</p>
            </article>
        `;
    }

    function adminQueueOverviewCard(label, value, description) {
        return `
            <article class="admin-mini-stat">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
                <p>${escapeHtml(description)}</p>
            </article>
        `;
    }

    function adminWithdrawalQueueRow(withdrawal) {
        return `
            <tr>
                <td>${escapeHtml(withdrawal.author?.name || 'Unknown')}</td>
                <td>${formatCredits(withdrawal.amount)}</td>
                <td>${formatDate(withdrawal.created_at)}</td>
                <td>${escapeHtml(withdrawal.reference_id)}</td>
                <td>
                    <div class="table-actions">
                        <button class="action-button approve compact-button" data-action="approve-withdrawal" data-withdrawal-id="${withdrawal.id}">Approve</button>
                        <button class="action-button reject compact-button" data-action="reject-withdrawal" data-withdrawal-id="${withdrawal.id}">Reject</button>
                    </div>
                </td>
            </tr>
        `;
    }

    function adminArticleQueueRow(article) {
        return `
            <tr>
                <td>${escapeHtml(article.title)}</td>
                <td>${escapeHtml(article.author?.name || 'Unknown')}</td>
                <td>${formatCredits(article.price)}</td>
                <td>${formatDate(article.updated_at)}</td>
                <td>
                    <div class="table-actions">
                        <button class="action-button approve compact-button" data-action="approve-article" data-article-id="${article.id}">Approve</button>
                        <button class="action-button reject compact-button" data-action="reject-article" data-article-id="${article.id}">Reject</button>
                    </div>
                </td>
            </tr>
        `;
    }

    function adminArticleCard(article) {
        return `
            <article class="admin-row-card">
                <div class="admin-row-heading">
                    <div>
                        <span class="queue-label">${prettyStatus(article.status)}</span>
                        <h4>${escapeHtml(article.title)}</h4>
                    </div>
                    <span class="price-tag">${formatCredits(article.price)}</span>
                </div>
                <p>${escapeHtml(article.preview_text || 'No preview text provided.')}</p>
                <div class="admin-row-meta">
                    <span>Author: ${escapeHtml(article.author?.name || 'Unknown')}</span>
                    <span>Email: ${escapeHtml(article.author?.email || '-')}</span>
                    <span>Commission: ${escapeHtml(article.commission_type)} ${escapeHtml(String(article.commission_value))}</span>
                    <span>Updated: ${formatDate(article.updated_at)}</span>
                </div>
                <div class="action-group">
                    <button class="action-button approve" type="button" data-action="approve-article" data-article-id="${article.id}">Approve</button>
                    <button class="action-button reject" type="button" data-action="reject-article" data-article-id="${article.id}">Reject</button>
                    <button class="ghost-button" type="button" data-action="open-article" data-slug="${escapeHtml(article.slug)}" data-title="${escapeHtml(article.title)}">Preview</button>
                </div>
            </article>
        `;
    }

    function adminWithdrawalCard(withdrawal) {
        return `
            <article class="admin-row-card">
                <div class="admin-row-heading">
                    <div>
                        <span class="queue-label">${prettyStatus(withdrawal.status)}</span>
                        <h4>${escapeHtml(withdrawal.author?.name || 'Unknown author')}</h4>
                    </div>
                    <span class="price-tag">${formatCredits(withdrawal.amount)}</span>
                </div>
                <p>${escapeHtml(withdrawal.reference_id || 'Manual withdrawal request awaiting admin confirmation.')}</p>
                <div class="admin-row-meta">
                    <span>Email: ${escapeHtml(withdrawal.author?.email || '-')}</span>
                    <span>Requested: ${formatDate(withdrawal.created_at)}</span>
                    <span>Reference: ${escapeHtml(withdrawal.reference_id || '-')}</span>
                </div>
                <div class="action-group">
                    <button class="action-button approve" type="button" data-action="approve-withdrawal" data-withdrawal-id="${withdrawal.id}">Approve</button>
                    <button class="action-button reject" type="button" data-action="reject-withdrawal" data-withdrawal-id="${withdrawal.id}">Reject</button>
                </div>
            </article>
        `;
    }

    function authorLeaderboardRow(author, index) {
        return `
            <tr>
                <td class="admin-cell-rank">#${index + 1}</td>
                <td>${adminIdentityCell(author, author.email, author.username ? `@${author.username}` : formatMaybe(author.phone))}</td>
                <td>${formatCount(author.stats.published_articles_count)}</td>
                <td>${formatCount(author.stats.unique_readers_count)}</td>
                <td>${formatCount(author.stats.total_views)}</td>
                <td>${formatCount(author.stats.total_unlocks)}</td>
                <td>${formatRating(author.stats.average_rating, author.stats.rating_votes)}</td>
                <td>${formatCredits(author.stats.realized_earnings)}</td>
                <td>${formatCredits(author.stats.pending_withdrawals_amount)}</td>
            </tr>
        `;
    }

    function performanceTableRow(article, index) {
        return `
            <tr>
                <td class="admin-cell-rank">#${index + 1}</td>
                <td>
                    <div class="admin-table-title">
                        <strong>${escapeHtml(article.title)}</strong>
                        <span>${prettyStatus(article.status)} · ${formatDate(article.published_at)}</span>
                    </div>
                </td>
                <td>${escapeHtml(article.author?.name || 'Unknown')}</td>
                <td>${escapeHtml(article.category || 'General')}</td>
                <td>${formatCount(article.view_count)}</td>
                <td>${formatCount(article.unlock_count)}</td>
                <td>${formatCount(article.unique_readers_count)}</td>
                <td>${formatRating(article.rating_average, article.rating_count)}</td>
                <td>${formatCredits(article.realized_earnings)}</td>
                <td>
                    <button class="ghost-button compact-button" type="button" data-action="open-article" data-slug="${escapeHtml(article.slug)}" data-title="${escapeHtml(article.title)}">Open</button>
                </td>
            </tr>
        `;
    }

    function userDirectoryRow(user) {
        return `
            <tr data-directory-row data-search="${escapeHtml(directorySearchValue(user))}">
                <td>${adminIdentityCell(user, user.email, user.username ? `@${user.username}` : `ID ${user.id}`)}</td>
                <td>${roleBadge(user.role)}</td>
                <td>
                    <div class="admin-table-title">
                        <strong>${escapeHtml(formatMaybe(user.phone))}</strong>
                        <span>${user.email_verified_at ? 'Verified account' : 'Email verification pending'}</span>
                    </div>
                </td>
                <td>${formatCredits(user.wallet_balance)}</td>
                <td>${formatDate(user.created_at)}</td>
                <td>${renderUserHighlights(user)}</td>
            </tr>
        `;
    }

    function authorLeaderboardCard(author, index) {
        return `
            <article class="admin-leaderboard-item">
                <div class="admin-leaderboard-rank">#${index + 1}</div>
                <div class="admin-leaderboard-copy">
                    ${adminIdentityCell(author, author.email, `${formatCount(author.stats.published_articles_count)} published articles`)}
                    <div class="admin-row-meta">
                        <span>${formatCount(author.stats.total_unlocks)} unlocks</span>
                        <span>${formatCredits(author.stats.realized_earnings)} earned</span>
                    </div>
                </div>
            </article>
        `;
    }

    function articleLeaderboardCard(article, index) {
        return `
            <article class="admin-leaderboard-item">
                <div class="admin-leaderboard-rank">#${index + 1}</div>
                <div class="admin-leaderboard-copy">
                    <div class="admin-table-title">
                        <strong>${escapeHtml(article.title)}</strong>
                        <span>${escapeHtml(article.author?.name || 'Unknown author')}</span>
                    </div>
                    <div class="admin-row-meta">
                        <span>${formatCount(article.unlock_count)} unlocks</span>
                        <span>${formatCount(article.view_count)} views</span>
                        <span>${formatCredits(article.realized_earnings)} earned</span>
                    </div>
                </div>
            </article>
        `;
    }

    function activityFeedItem(item) {
        return `
            <article class="admin-activity-item tone-${escapeHtml(item.tone || 'neutral')}">
                <div class="admin-activity-copy">
                    <span>${escapeHtml(item.label)}</span>
                    <strong>${escapeHtml(item.title)}</strong>
                    <p>${escapeHtml(item.meta)}</p>
                </div>
                <time datetime="${escapeHtml(item.timestamp || '')}">${escapeHtml(formatDate(item.timestamp))}</time>
            </article>
        `;
    }

    function authorInsightCard(author) {
        return `
            <article class="surface section-block admin-author-card">
                <div class="admin-author-header">
                    <div class="admin-author-intro">
                        ${adminIdentityCell(author, author.email, author.username ? `@${author.username}` : 'Author account')}
                        <div class="admin-author-contact">
                            <span>${escapeHtml(formatMaybe(author.phone))}</span>
                            <span>Joined ${formatDate(author.created_at)}</span>
                            <span>Wallet ${formatCredits(author.wallet_balance)}</span>
                        </div>
                    </div>

                    <div class="metric-chip-list">
                        ${metricChip('Published', formatCount(author.stats.published_articles_count))}
                        ${metricChip('Views', formatCount(author.stats.total_views))}
                        ${metricChip('Unlocks', formatCount(author.stats.total_unlocks))}
                        ${metricChip('Readers', formatCount(author.stats.unique_readers_count))}
                        ${metricChip('Rating', formatRating(author.stats.average_rating, author.stats.rating_votes))}
                        ${metricChip('Earned', formatCredits(author.stats.realized_earnings), 'good')}
                        ${metricChip('Pending', formatCredits(author.stats.pending_withdrawals_amount), author.stats.pending_withdrawals_amount ? 'warn' : 'neutral')}
                    </div>
                </div>

                <div class="admin-table-shell">
                    ${author.articles.length
                        ? `
                            <table class="admin-data-table compact">
                                <thead>
                                    <tr>
                                        <th>Article</th>
                                        <th>Status</th>
                                        <th>Price</th>
                                        <th>Views</th>
                                        <th>Unlocks</th>
                                        <th>Readers</th>
                                        <th>Rating</th>
                                        <th>Earnings</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${author.articles.map(authorArticleTableRow).join('')}
                                </tbody>
                            </table>
                        `
                        : emptyState('This author has not published any articles yet.')}
                </div>
            </article>
        `;
    }

    function authorArticleTableRow(article) {
        return `
            <tr>
                <td>
                    <div class="admin-table-title">
                        <strong>${escapeHtml(article.title)}</strong>
                        <span>${escapeHtml(article.category || 'General')} · ${formatDate(article.published_at || article.updated_at)}</span>
                    </div>
                </td>
                <td>${statusBadge(article.status)}</td>
                <td>${formatCredits(article.price)}</td>
                <td>${formatCount(article.view_count)}</td>
                <td>${formatCount(article.unlock_count)}</td>
                <td>${formatCount(article.unique_readers_count)}</td>
                <td>${formatRating(article.rating_average, article.rating_count)}</td>
                <td>${formatCredits(article.realized_earnings)}</td>
                <td>
                    <button class="ghost-button compact-button" type="button" data-action="open-article" data-slug="${escapeHtml(article.slug)}" data-title="${escapeHtml(article.title)}">Open</button>
                </td>
            </tr>
        `;
    }

    function adminIdentityCell(person, subtitle, meta) {
        return `
            <div class="admin-identity">
                ${avatarMarkup(person)}
                <div class="admin-identity-copy">
                    <strong>${escapeHtml(person.name || 'Unknown')}</strong>
                    <span>${escapeHtml(subtitle || '-')}</span>
                    ${meta ? `<small>${escapeHtml(meta)}</small>` : ''}
                </div>
            </div>
        `;
    }

    function avatarMarkup(person) {
        if (person.profile_photo_url) {
            return `<img class="admin-avatar image" src="${escapeHtml(person.profile_photo_url)}" alt="${escapeHtml(person.name || 'User')}">`;
        }

        return `<span class="admin-avatar">${escapeHtml(initialsFromName(person.name))}</span>`;
    }

    function metricChip(label, value, tone = 'neutral') {
        return `
            <span class="metric-chip tone-${escapeHtml(tone)}">
                <em>${escapeHtml(label)}</em>
                <strong>${escapeHtml(value)}</strong>
            </span>
        `;
    }

    function roleBadge(role) {
        return `<span class="admin-role-badge role-${escapeHtml(role || 'user')}">${escapeHtml(prettyRole(role))}</span>`;
    }

    function statusBadge(status) {
        const tone = String(status || '').includes('publish')
            ? 'good'
            : String(status || '').includes('reject')
                ? 'danger'
                : String(status || '').includes('pending')
                    ? 'warn'
                    : 'neutral';

        return `<span class="admin-inline-badge tone-${tone}">${escapeHtml(prettyStatus(status))}</span>`;
    }

    function renderUserHighlights(user) {
        if (user.role === 'author') {
            return `
                <div class="metric-chip-list">
                    ${metricChip('Published', formatCount(user.stats.published_articles_count))}
                    ${metricChip('Audience', formatCount(user.stats.unique_readers_count))}
                    ${metricChip('Unlocks', formatCount(user.stats.total_unlocks))}
                    ${metricChip('Rating', formatRating(user.stats.average_rating, user.stats.rating_votes))}
                    ${metricChip('Earned', formatCredits(user.stats.realized_earnings), 'good')}
                </div>
            `;
        }

        if (user.role === 'reader') {
            return `
                <div class="metric-chip-list">
                    ${metricChip('Orders', formatCount(user.stats.paid_orders_count))}
                    ${metricChip('Purchased', formatCredits(user.stats.credits_purchased))}
                    ${metricChip('Unlocked', formatCount(user.stats.unlocked_articles_count))}
                    ${metricChip('Spent', formatRupees(user.stats.amount_spent_rupees))}
                </div>
            `;
        }

        return `
            <div class="metric-chip-list">
                ${metricChip('Authors', formatCount(user.stats.managed_authors_count))}
                ${metricChip('Readers', formatCount(user.stats.managed_readers_count))}
                ${metricChip('Open Queue', formatCount(user.stats.open_queue_count), user.stats.open_queue_count ? 'warn' : 'good')}
            </div>
        `;
    }

    function initializeArticleComposer() {
        elements.dashboardContent
            .querySelectorAll('[data-editor-input]')
            .forEach((textarea) => updateEditorCount(textarea));

        const imageInput = elements.dashboardContent.querySelector('[data-article-image-input]');
        if (imageInput instanceof HTMLInputElement) {
            syncArticleImagePreview(imageInput);
        }
    }

    function applyEditorCommand(button) {
        const targetName = button.dataset.editorTarget || '';
        const command = button.dataset.editorCommand || '';
        const textarea = elements.dashboardContent.querySelector(`[data-editor-input][name="${targetName}"]`);

        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        if (command === 'paragraph') {
            insertEditorText(textarea, '\n\n');
        }

        if (command === 'bullet') {
            prefixEditorLines(textarea, '• ');
        }

        if (command === 'quote') {
            prefixEditorLines(textarea, '> ');
        }

        if (command === 'divider') {
            insertEditorText(textarea, '\n--------------------\n');
        }

        textarea.focus();
        updateEditorCount(textarea);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function insertEditorText(textarea, text) {
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? start;
        textarea.setRangeText(text, start, end, 'end');
    }

    function prefixEditorLines(textarea, prefix) {
        const value = textarea.value;
        const selectionStart = textarea.selectionStart ?? 0;
        const selectionEnd = textarea.selectionEnd ?? selectionStart;
        const lineStart = value.lastIndexOf('\n', Math.max(0, selectionStart - 1)) + 1;
        const lineEndIndex = value.indexOf('\n', selectionEnd);
        const lineEnd = lineEndIndex === -1 ? value.length : lineEndIndex;
        const selectedBlock = value.slice(lineStart, lineEnd);
        const transformed = selectedBlock
            .split('\n')
            .map((line) => (line.trim() ? `${prefix}${line}` : prefix.trimEnd()))
            .join('\n');

        textarea.setRangeText(transformed, lineStart, lineEnd, 'select');
    }

    function updateEditorCount(textarea) {
        if (!(textarea instanceof HTMLTextAreaElement)) {
            return;
        }

        const counter = elements.dashboardContent.querySelector(`[data-editor-count="${textarea.name}"]`);
        if (!counter) {
            return;
        }

        const words = countWords(textarea.value);
        counter.textContent = words === 1 ? '1 word' : `${formatCount(words)} words`;
    }

    function syncArticleImagePreview(input) {
        const preview = elements.dashboardContent.querySelector('[data-article-image-preview]');
        const imageTag = elements.dashboardContent.querySelector('[data-article-image-tag]');
        const nameTag = elements.dashboardContent.querySelector('[data-article-image-name]');

        if (!preview || !(imageTag instanceof HTMLImageElement) || !nameTag) {
            return;
        }

        revokeArticleImagePreview();

        const [file] = input.files || [];

        if (!file) {
            preview.classList.add('hidden');
            imageTag.removeAttribute('src');
            nameTag.textContent = 'No file selected';
            return;
        }

        state.articleImagePreviewUrl = URL.createObjectURL(file);
        imageTag.src = state.articleImagePreviewUrl;
        nameTag.textContent = `${file.name} · ${formatFileSize(file.size)}`;
        preview.classList.remove('hidden');
    }

    function revokeArticleImagePreview() {
        if (!state.articleImagePreviewUrl) {
            return;
        }

        URL.revokeObjectURL(state.articleImagePreviewUrl);
        state.articleImagePreviewUrl = null;
    }

    function buildAdminActivityFeed(users, pendingArticles, pendingWithdrawals) {
        return [
            ...users.slice(0, 4).map((user) => ({
                label: 'New User',
                title: `${user.name} joined as ${prettyRole(user.role)}`,
                meta: user.email,
                timestamp: user.created_at,
                tone: user.role === 'author' ? 'good' : 'neutral',
            })),
            ...pendingWithdrawals.slice(0, 2).map((withdrawal) => ({
                label: 'Payout Request',
                title: `${withdrawal.author?.name || 'Unknown'} requested ${formatCredits(withdrawal.amount)}`,
                meta: withdrawal.reference_id || 'Manual withdrawal request',
                timestamp: withdrawal.created_at,
                tone: 'warn',
            })),
            ...pendingArticles.slice(0, 2).map((article) => ({
                label: 'Article Review',
                title: `${article.title} is waiting for approval`,
                meta: article.author?.name || 'Unknown author',
                timestamp: article.updated_at || article.created_at,
                tone: 'warn',
            })),
        ]
            .filter((item) => item.timestamp)
            .sort((left, right) => timestampValue(right.timestamp) - timestampValue(left.timestamp))
            .slice(0, 6);
    }

    function filterAdminDirectory(query) {
        const rows = Array.from(elements.dashboardContent.querySelectorAll('[data-directory-row]'));

        if (!rows.length) {
            return;
        }

        const term = String(query || '').trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach((row) => {
            const matches = !term || row.dataset.search?.includes(term);
            row.classList.toggle('hidden', !matches);

            if (matches) {
                visibleCount += 1;
            }
        });

        const countNode = elements.dashboardContent.querySelector('[data-directory-count]');
        const emptyNode = elements.dashboardContent.querySelector('[data-directory-empty]');

        if (countNode) {
            countNode.textContent = term
                ? `${formatCount(visibleCount)} of ${formatCount(rows.length)} users`
                : `${formatCount(rows.length)} users`;
        }

        if (emptyNode) {
            emptyNode.classList.toggle('hidden', visibleCount !== 0);
        }
    }

    function exportAdminUsers() {
        if (!state.adminDirectoryUsers.length) {
            showToast('No users available to export yet.', 'error');
            return;
        }

        const headers = ['Name', 'Role', 'Email', 'Phone', 'Wallet Credits', 'Joined', 'Verified'];
        const csvRows = state.adminDirectoryUsers.map((user) => [
            csvCell(user.name),
            csvCell(prettyRole(user.role)),
            csvCell(user.email),
            csvCell(formatMaybe(user.phone)),
            csvCell(Number(user.wallet_balance || 0)),
            csvCell(formatDate(user.created_at)),
            csvCell(user.email_verified_at ? 'Yes' : 'No'),
        ]);

        const csv = [headers, ...csvRows]
            .map((row) => row.join(','))
            .join('\n');

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const stamp = new Date().toISOString().slice(0, 10);

        link.href = url;
        link.download = `admin-user-directory-${stamp}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);

        showToast('User directory exported.', 'success');
    }

    function performanceCard(article, index = 0) {
        const grossCredits = Number(article.price || 0) * Number(article.unlock_count || 0);

        return `
            <article class="admin-row-card compact">
                <div class="admin-performance-layout">
                    <div class="admin-rank">#${index + 1}</div>
                    <div class="admin-performance-copy">
                        <span class="queue-label">${escapeHtml(article.author?.name || 'Unknown')}</span>
                        <h4>${escapeHtml(article.title)}</h4>
                        <div class="admin-row-meta">
                            <span>${formatCount(article.unlock_count)} unlocks</span>
                            <span>${formatCount(article.view_count)} views</span>
                            <span>${formatCredits(article.price)} per unlock</span>
                            <span>${formatCount(grossCredits)} gross credits</span>
                        </div>
                    </div>
                </div>
            </article>
        `;
    }

    function catalogCard(article) {
        const accessLabel = article.access_duration_hours
            ? `${formatCount(article.access_duration_hours)} hour access`
            : 'Lifetime access';

        return `
            <article class="admin-row-card compact">
                <div class="admin-row-heading">
                    <div>
                        <span class="queue-label">${escapeHtml(article.author?.name || 'Author')}</span>
                        <h4>${escapeHtml(article.title)}</h4>
                    </div>
                    <span class="price-tag">${formatCredits(article.price)}</span>
                </div>
                <p>${escapeHtml(article.preview_text || 'No preview text available.')}</p>
                <div class="admin-row-meta">
                    <span>${formatCount(article.unlock_count)} unlocks</span>
                    <span>${formatCount(article.view_count)} views</span>
                    <span>${escapeHtml(accessLabel)}</span>
                </div>
                <div class="action-group">
                    <button class="ghost-button" type="button" data-action="open-article" data-slug="${escapeHtml(article.slug)}" data-title="${escapeHtml(article.title)}">Open</button>
                </div>
            </article>
        `;
    }

    function authorArticleCard(article) {
        return `
            <article class="queue-card">
                <div class="card-head">
                    <div>
                        <span class="queue-label">${prettyStatus(article.status)}</span>
                        <h3>${escapeHtml(article.title)}</h3>
                    </div>
                    <span class="price-tag">${formatCredits(article.price)}</span>
                </div>
                <p>${escapeHtml(article.preview_text || '')}</p>
                <div class="queue-meta">
                    <span>Commission: ${escapeHtml(article.commission_type)} ${escapeHtml(String(article.commission_value))}</span>
                    <span>Views: ${article.view_count}</span>
                    <span>Unlocks: ${article.unlock_count}</span>
                </div>
            </article>
        `;
    }

    function authorWithdrawalCard(withdrawal) {
        return `
            <article class="queue-card">
                <div class="card-head">
                    <div>
                        <span class="queue-label">${prettyStatus(withdrawal.status)}</span>
                        <h3>${escapeHtml(withdrawal.reference_id || 'Withdrawal')}</h3>
                    </div>
                    <span class="price-tag">${formatCredits(withdrawal.amount)}</span>
                </div>
                <div class="queue-meta">
                    <span>Requested: ${formatDate(withdrawal.created_at)}</span>
                    <span>Processed: ${withdrawal.processed_at ? formatDate(withdrawal.processed_at) : 'Pending'}</span>
                    <span>${escapeHtml(withdrawal.admin_notes || 'No admin notes yet')}</span>
                </div>
            </article>
        `;
    }

    function readerCatalogCard(article) {
        return `
            <article class="catalog-card">
                <header>
                    <div>
                        <span class="queue-label">${escapeHtml(article.author?.name || 'Author')}</span>
                        <h3>${escapeHtml(article.title)}</h3>
                    </div>
                    <span class="price-tag">${formatCredits(article.price)}</span>
                </header>
                <p>${escapeHtml(article.preview_text || '')}</p>
                <div class="catalog-meta">
                    <span>${article.access_duration_hours ? `${article.access_duration_hours} hour access` : 'Lifetime access'}</span>
                    <span>${article.unlock_count} unlocks</span>
                </div>
                <div class="action-group">
                    <button class="ghost-button" type="button" data-action="open-article" data-slug="${escapeHtml(article.slug)}" data-title="${escapeHtml(article.title)}">
                        ${article.is_unlocked ? 'Read Article' : 'View Preview'}
                    </button>
                    ${article.is_unlocked
                        ? ''
                        : `<button class="primary-button" type="button" data-action="unlock-article" data-slug="${escapeHtml(article.slug)}" data-title="${escapeHtml(article.title)}">Unlock</button>`}
                </div>
            </article>
        `;
    }

    function unlockCard(unlock) {
        return `
            <article class="queue-card">
                <div class="card-head">
                    <div>
                        <span class="queue-label">${unlock.is_active ? 'Active' : 'Expired'}</span>
                        <h3>${escapeHtml(unlock.article?.title || 'Article')}</h3>
                    </div>
                    <span class="price-tag">${formatCredits(unlock.credits_spent)}</span>
                </div>
                <div class="queue-meta">
                    <span>Author: ${escapeHtml(unlock.article?.author?.name || 'Unknown')}</span>
                    <span>Unlocked: ${formatDate(unlock.unlocked_at)}</span>
                    <span>${unlock.expires_at ? `Expires: ${formatDate(unlock.expires_at)}` : 'Lifetime access'}</span>
                </div>
                <div class="action-group">
                    <button class="ghost-button" type="button" data-action="open-article" data-slug="${escapeHtml(unlock.article?.slug || '')}" data-title="${escapeHtml(unlock.article?.title || 'Article')}">Open</button>
                </div>
            </article>
        `;
    }

    function emptyState(message) {
        return `<div class="empty-state">${message}</div>`;
    }

    function loadingMarkup() {
        return `
            <div class="surface section-block">
                <div class="empty-state">Loading dashboard data...</div>
            </div>
        `;
    }

    function formatCredits(value) {
        return `${Number(value || 0).toLocaleString('en-IN')} credits`;
    }

    function formatCount(value) {
        return Number(value || 0).toLocaleString('en-IN');
    }

    function formatRating(value, votes = null) {
        const normalized = Number(value || 0).toFixed(1);

        if (votes === null || votes === undefined || votes === '') {
            return `${normalized}/5`;
        }

        return `${normalized}/5 · ${formatCount(votes)} votes`;
    }

    function formatRupees(value) {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function formatDate(value) {
        if (!value) {
            return 'Not available';
        }

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat('en-IN', {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(date);
    }

    function timestampValue(value) {
        const parsed = new Date(value || '').getTime();
        return Number.isNaN(parsed) ? 0 : parsed;
    }

    function countWords(value) {
        const normalized = String(value || '').trim();
        return normalized ? normalized.split(/\s+/).length : 0;
    }

    function formatFileSize(bytes) {
        const size = Number(bytes || 0);

        if (size >= 1024 * 1024) {
            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        }

        if (size >= 1024) {
            return `${Math.round(size / 1024)} KB`;
        }

        return `${size} B`;
    }

    function prettyRole(role) {
        if (!role) {
            return 'User';
        }

        return String(role).charAt(0).toUpperCase() + String(role).slice(1);
    }

    function prettyStatus(status) {
        return String(status || '')
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase());
    }

    function formatMaybe(value, fallback = '-') {
        const normalized = String(value ?? '').trim();
        return normalized || fallback;
    }

    function directorySearchValue(user) {
        return [
            user.name,
            user.email,
            user.phone,
            user.username,
            prettyRole(user.role),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
    }

    function csvCell(value) {
        return `"${String(value ?? '').replaceAll('"', '""')}"`;
    }

    function initialsFromName(name) {
        const parts = String(name || '')
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2);

        if (!parts.length) {
            return 'U';
        }

        return parts.map((part) => part.charAt(0).toUpperCase()).join('');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function readStoredUser() {
        try {
            return JSON.parse(localStorage.getItem('cmp_user') || 'null');
        } catch (_error) {
            return null;
        }
    }
})();
