(function () {
    const state = {
        token: localStorage.getItem('cmp_token') || null,
        user: readStoredUser(),
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

        elements.refreshButton.classList.remove('hidden');
        elements.workspaceRoleBadge.textContent = prettyRole(state.user.role);
        elements.sidebarName.textContent = state.user.name;
        elements.sidebarRole.textContent = prettyRole(state.user.role);
        elements.dashboardContent.innerHTML = loadingMarkup();

        if (state.user.role === 'admin') {
            await renderAdminDashboard();
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
            const [dashboard, pendingArticles, pendingWithdrawals, publicArticles] = await Promise.all([
                api('/api/admin/dashboard'),
                api('/api/admin/articles/pending'),
                api('/api/admin/withdrawals/pending'),
                api('/api/articles'),
            ]);

            updateShell({
                eyebrow: 'Admin Console',
                title: 'Revenue, withdrawals, and platform control',
                subtitle: 'Authors publish articles directly now. Use this space for payouts, analytics, and any legacy pending items.',
                walletBalance: state.user.wallet_balance || 0,
                hint: 'You are viewing the platform-wide control room.',
            });

            elements.dashboardContent.innerHTML = `
                <section class="stat-grid">
                    ${summaryCard('Revenue', formatRupees(dashboard.summary.total_revenue_rupees), 'Gross money captured through credit sales')}
                    ${summaryCard('Credits Sold', formatCredits(dashboard.summary.total_credits_sold), 'Total credits issued to readers and authors')}
                    ${summaryCard('Author Earnings', formatCredits(dashboard.summary.author_earnings), 'Credits moved to authors from unlocks')}
                    ${summaryCard('Commission Earned', formatCredits(dashboard.summary.commission_earned), 'Platform share retained from article unlocks')}
                </section>

                <section class="split-layout">
                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Legacy Queue</p>
                                <h3>Pending Articles</h3>
                            </div>
                            <span class="badge">${pendingArticles.articles.length} waiting</span>
                        </div>
                        <div class="queue-list">
                            ${pendingArticles.articles.length
                                ? pendingArticles.articles.map(adminArticleCard).join('')
                                : emptyState('Authors publish directly now, so no legacy pending articles remain.')}
                        </div>
                    </div>

                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Finance Queue</p>
                                <h3>Pending Withdrawals</h3>
                            </div>
                            <span class="badge">${pendingWithdrawals.withdrawals.length} requests</span>
                        </div>
                        <div class="queue-list">
                            ${pendingWithdrawals.withdrawals.length
                                ? pendingWithdrawals.withdrawals.map(adminWithdrawalCard).join('')
                                : emptyState('No withdrawal requests are pending.')}
                        </div>
                    </div>
                </section>

                <section class="split-layout">
                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Leaderboard</p>
                                <h3>Article Performance</h3>
                            </div>
                            <span class="badge">Top ${dashboard.article_performance.length}</span>
                        </div>
                        <div class="queue-list">
                            ${dashboard.article_performance.length
                                ? dashboard.article_performance.map(performanceCard).join('')
                                : emptyState('No article performance data yet.')}
                        </div>
                    </div>

                    <div class="surface section-block">
                        <div class="section-head">
                            <div>
                                <p class="eyebrow">Live Catalog</p>
                                <h3>Published Articles</h3>
                            </div>
                            <span class="badge">${publicArticles.articles.length} live</span>
                        </div>
                        <div class="catalog-grid">
                            ${publicArticles.articles.length
                                ? publicArticles.articles.slice(0, 6).map(catalogCard).join('')
                                : emptyState('Published articles will appear here as soon as authors post them.')}
                        </div>
                    </div>
                </section>
            `;
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
                walletBalance: wallet.wallet_balance,
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
                                <span>Image URL</span>
                                <input name="image_url" type="url" placeholder="https://...">
                            </label>

                            <label class="field">
                                <span>Preview Text</span>
                                <textarea name="preview_text" required></textarea>
                            </label>

                            <label class="field">
                                <span>Full Content</span>
                                <textarea name="content" required></textarea>
                            </label>

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
                walletBalance: wallet.wallet_balance,
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
                await api('/api/articles', {
                    method: 'POST',
                    body: {
                        title: String(formData.get('title') || ''),
                        image_url: String(formData.get('image_url') || ''),
                        preview_text: String(formData.get('preview_text') || ''),
                        content: String(formData.get('content') || ''),
                        price: Number(formData.get('price') || 0),
                        commission_type: String(formData.get('commission_type') || 'percentage'),
                        commission_value: Number(formData.get('commission_value') || 0),
                        access_duration_hours: Number(formData.get('access_duration_hours') || 24),
                    },
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
            fetchOptions.body = JSON.stringify(options.body);
            fetchOptions.headers['Content-Type'] = 'application/json';
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

    function updateShell({ eyebrow, title, subtitle, walletBalance, hint }) {
        elements.workspaceEyebrow.textContent = eyebrow;
        elements.workspaceTitle.textContent = title;
        elements.workspaceSubtitle.textContent = subtitle;
        elements.sidebarWallet.textContent = formatCredits(walletBalance || 0);
        elements.sidebarHint.textContent = hint;
    }

    function renderError(error) {
        elements.dashboardContent.innerHTML = emptyState(
            escapeHtml(error.message || 'Something went wrong while loading the dashboard.'),
        );
    }

    function showAuth() {
        elements.appScreen.classList.add('hidden');
        elements.authScreen.classList.remove('hidden');
        elements.refreshButton.classList.add('hidden');
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
        state.token = null;
        state.user = null;
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

    function adminArticleCard(article) {
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
                    <span>Author: ${escapeHtml(article.author?.name || 'Unknown')}</span>
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
            <article class="queue-card">
                <div class="card-head">
                    <div>
                        <span class="queue-label">${prettyStatus(withdrawal.status)}</span>
                        <h3>${escapeHtml(withdrawal.author?.name || 'Unknown author')}</h3>
                    </div>
                    <span class="price-tag">${formatCredits(withdrawal.amount)}</span>
                </div>
                <div class="queue-meta">
                    <span>Email: ${escapeHtml(withdrawal.author?.email || '-')}</span>
                    <span>Reference: ${escapeHtml(withdrawal.reference_id || '-')}</span>
                    <span>Requested: ${formatDate(withdrawal.created_at)}</span>
                </div>
                <div class="action-group">
                    <button class="action-button approve" type="button" data-action="approve-withdrawal" data-withdrawal-id="${withdrawal.id}">Approve</button>
                    <button class="action-button reject" type="button" data-action="reject-withdrawal" data-withdrawal-id="${withdrawal.id}">Reject</button>
                </div>
            </article>
        `;
    }

    function performanceCard(article) {
        return `
            <article class="stat-card">
                <span>${escapeHtml(article.author?.name || 'Unknown')}</span>
                <strong>${escapeHtml(article.title)}</strong>
                <p>${article.unlock_count} unlocks · ${article.view_count} views · ${formatCredits(article.price)}</p>
            </article>
        `;
    }

    function catalogCard(article) {
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
                    <span>${article.unlock_count} unlocks</span>
                    <span>${article.view_count} views</span>
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
