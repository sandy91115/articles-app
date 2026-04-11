<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\ArticleUnlockService;
use App\Services\RazorpayService;
use App\Services\ReaderPortalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ReaderPortalController extends Controller
{
    public function __construct(
        protected ReaderPortalService $portal,
        protected ArticleUnlockService $articleUnlockService,
        protected RazorpayService $razorpayService,
    ) {}

    public function home(Request $request): View
    {
        $bundle = $this->portal->bundle($request->user());
        $selectedCategory = $this->selectCategory(
            $request->string('category')->toString(),
            $bundle['category_options'],
        );
        $selectedAuthor = $this->selectAuthor(
            $request->string('author')->toString(),
            $bundle['author_options'],
        );
        $query = trim($request->string('q')->toString());

        $filteredArticles = $this->portal->filteredArticles(
            $bundle['articles'],
            $query,
            $selectedCategory,
            $selectedAuthor,
        );
        $topArticles = $this->portal->sortTopArticles($filteredArticles);
        $leadArticle = $topArticles->first();
        $highlightedArticles = $topArticles
            ->reject(fn (array $article): bool => $leadArticle !== null && $article['id'] === $leadArticle['id'])
            ->take(4)
            ->values();
        $quickPicks = $filteredArticles
            ->reject(fn (array $article): bool => $leadArticle !== null && $article['id'] === $leadArticle['id'])
            ->take(5)
            ->values();
        $latestArticles = $filteredArticles->take(6)->values();

        return view('reader.home', [
            'activeTab' => 'home',
            'bundle' => $bundle,
            'query' => $query,
            'selectedCategory' => $selectedCategory,
            'selectedAuthor' => $selectedAuthor,
            'filteredArticles' => $filteredArticles,
            'topArticles' => $topArticles,
            'leadArticle' => $leadArticle,
            'highlightedArticles' => $highlightedArticles,
            'quickPicks' => $quickPicks,
            'latestArticles' => $latestArticles,
            'categoryGroups' => $this->portal->categoryGroups($filteredArticles, $selectedCategory),
            'showRechargePrompt' => $request->session()->pull('reader_prompt_wallet', false)
                && $this->portal->shouldPromptRecharge($bundle),
        ]);
    }

    public function search(Request $request): View
    {
        $bundle = $this->portal->bundle($request->user());
        $selectedCategory = $this->selectCategory(
            $request->string('category')->toString(),
            $bundle['category_options'],
        );
        $query = trim($request->string('q')->toString());
        $filteredArticles = $this->portal->filteredArticles(
            $bundle['articles'],
            $query,
            $selectedCategory,
        );

        return view('reader.search', [
            'activeTab' => 'search',
            'bundle' => $bundle,
            'query' => $query,
            'selectedCategory' => $selectedCategory,
            'filteredArticles' => $filteredArticles,
        ]);
    }

    public function library(Request $request): View
    {
        $bundle = $this->portal->bundle($request->user());

        return view('reader.library', [
            'activeTab' => 'library',
            'bundle' => $bundle,
            'boughtItems' => $bundle['bought_items'],
        ]);
    }

    public function wallet(Request $request): View
    {
        $bundle = $this->portal->bundle($request->user());

        return view('reader.wallet', [
            'activeTab' => 'wallet',
            'bundle' => $bundle,
            'minimumTopUpRupees' => $this->portal->minimumTopUpRupees($bundle['wallet']),
        ]);
    }

    public function profile(Request $request): View
    {
        $bundle = $this->portal->bundle($request->user());

        return view('reader.profile', [
            'activeTab' => 'profile',
            'bundle' => $bundle,
        ]);
    }

    public function showArticle(Request $request, Article $article): View
    {
        $detail = $this->portal->detail($request->user(), $article);
        $bundle = $this->portal->bundle($request->user());

        return view('reader.article', [
            'activeTab' => null,
            'bundle' => $bundle,
            'article' => $detail,
        ]);
    }

    public function unlockArticle(Request $request, Article $article): RedirectResponse
    {
        $this->articleUnlockService->unlock($request->user(), $article);

        return redirect()
            ->route('reader.articles.show', $article)
            ->with('status', 'Article unlocked successfully.');
    }

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'credits' => ['required', 'integer', 'min:1'],
        ]);

        $order = $this->razorpayService->createOrder($request->user(), (int) $validated['credits']);

        return redirect()
            ->route('reader.wallet')
            ->with('status', 'Payment order created successfully.')
            ->with('wallet_order', [
                'reference' => $order->reference,
                'credit_amount' => $order->credit_amount,
                'provider_order_id' => $order->provider_order_id,
                'amount_label' => 'Rs '.number_format($order->amount_in_paise / 100, 2),
            ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();
        $photo = $validated['photo'];
        $directory = public_path('uploads/profile-photos');

        File::ensureDirectoryExists($directory);

        $filename = (string) Str::uuid().'.'.$photo->getClientOriginalExtension();
        $photo->move($directory, $filename);

        if ($user->profile_photo_url) {
            $existingPath = public_path(ltrim($user->profile_photo_url, '/'));
            if (File::exists($existingPath)) {
                File::delete($existingPath);
            }
        }

        $user->forceFill([
            'profile_photo_url' => '/uploads/profile-photos/'.$filename,
        ])->save();

        return redirect()
            ->route('reader.profile')
            ->with('status', 'Profile photo updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return redirect()
            ->route('reader.profile')
            ->with('status', 'Password updated successfully.');
    }

    protected function selectCategory(string $category, $options): string
    {
        return $options->contains($category) ? $category : 'All';
    }

    protected function selectAuthor(string $author, $options): string
    {
        return $options->contains($author) ? $author : 'All authors';
    }
}
