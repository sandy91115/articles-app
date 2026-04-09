<?php

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\CommissionType;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Platform Admin',
            'username' => 'platform-admin',
            'password' => 'password',
            'role' => UserRole::ADMIN,
            'wallet_balance' => 0,
            'email_verified_at' => now(),
        ]);

        $author = User::query()->updateOrCreate([
            'email' => 'author@example.com',
        ], [
            'name' => 'Demo Author',
            'username' => 'demo-author',
            'password' => 'password',
            'role' => UserRole::AUTHOR,
            'wallet_balance' => 500,
            'email_verified_at' => now(),
        ]);

        $priya = User::query()->updateOrCreate([
            'email' => 'priya.nair@example.com',
        ], [
            'name' => 'Priya Nair',
            'username' => 'priya-nair',
            'password' => 'password',
            'role' => UserRole::AUTHOR,
            'wallet_balance' => 420,
            'email_verified_at' => now(),
        ]);

        $arjun = User::query()->updateOrCreate([
            'email' => 'arjun.mehta@example.com',
        ], [
            'name' => 'Arjun Mehta',
            'username' => 'arjun-mehta',
            'password' => 'password',
            'role' => UserRole::AUTHOR,
            'wallet_balance' => 390,
            'email_verified_at' => now(),
        ]);

        User::query()->updateOrCreate([
            'email' => 'reader@example.com',
        ], [
            'name' => 'Demo Reader',
            'username' => 'demo-reader',
            'password' => 'password',
            'role' => UserRole::READER,
            'wallet_balance' => 300,
            'email_verified_at' => now(),
        ]);

        $articles = [
            [
                'slug' => 'welcome-to-paid-content',
                'author_id' => $author->id,
                'category' => 'Technology',
                'title' => 'Welcome To Paid Content',
                'image_url' => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'A quick introduction to how premium reading, wallet unlocks, and curated recommendations work inside the app.',
                'content' => 'Premium stories in the reader app are designed for fast discovery and deeper reading. Readers can top up coins, unlock what matters, and keep their library focused instead of cluttered. This sample story explains the core paid-content flow while also giving the home screen something meaningful to highlight.',
                'price' => 50,
                'commission_type' => CommissionType::PERCENTAGE,
                'commission_value' => 10,
                'access_duration_hours' => 24,
                'view_count' => 2840,
                'unlock_count' => 412,
                'rating_average' => 4.8,
                'rating_count' => 168,
                'published_at' => now()->subHours(2),
            ],
            [
                'slug' => 'ai-companies-are-racing-to-build-indian-language-assistants',
                'author_id' => $priya->id,
                'category' => 'Technology',
                'title' => 'AI Companies Are Racing To Build Indian-Language Assistants',
                'image_url' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Voice, translation, and search products are shifting from English-first experiences to local-language utility at scale.',
                'content' => 'Regional-language AI is no longer a side experiment. Product teams are investing in speech quality, translation accuracy, and on-device performance because the next wave of readers wants tools that sound natural in everyday use. This shift is creating new opportunities for publishers, educators, and consumer apps.',
                'price' => 65,
                'commission_type' => CommissionType::PERCENTAGE,
                'commission_value' => 12,
                'access_duration_hours' => 48,
                'view_count' => 3620,
                'unlock_count' => 533,
                'rating_average' => 4.9,
                'rating_count' => 214,
                'published_at' => now()->subHours(5),
            ],
            [
                'slug' => 'policy-watch-how-state-elections-are-shaping-digital-campaigns',
                'author_id' => $priya->id,
                'category' => 'Politics',
                'title' => 'Policy Watch: How State Elections Are Shaping Digital Campaigns',
                'image_url' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Campaign teams are putting more budget into explainers, short video, and hyperlocal issue tracking.',
                'content' => 'Digital campaigning is becoming less about raw reach and more about repeated trust signals. Teams are testing compact explainers, candidate issue diaries, and neighbourhood-specific messaging. For readers, that means a sharper stream of political content with more context and less generic noise.',
                'price' => 55,
                'commission_type' => CommissionType::FIXED,
                'commission_value' => 8,
                'access_duration_hours' => 24,
                'view_count' => 3010,
                'unlock_count' => 468,
                'rating_average' => 4.6,
                'rating_count' => 143,
                'published_at' => now()->subHours(8),
            ],
            [
                'slug' => 'startup-funding-signals-a-cautious-comeback-for-saas-founders',
                'author_id' => $author->id,
                'category' => 'Business',
                'title' => 'Startup Funding Signals A Cautious Comeback For SaaS Founders',
                'image_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Investors are still selective, but disciplined SaaS companies are finding their way back into meetings and term sheets.',
                'content' => 'The comeback in SaaS funding is measured rather than loud. Founders who can show efficient growth, tight retention, and a believable AI roadmap are reopening conversations that were frozen just months ago. Readers tracking startup cycles are paying attention to these early signals.',
                'price' => 60,
                'commission_type' => CommissionType::PERCENTAGE,
                'commission_value' => 10,
                'access_duration_hours' => 48,
                'view_count' => 2550,
                'unlock_count' => 366,
                'rating_average' => 4.7,
                'rating_count' => 128,
                'published_at' => now()->subHours(11),
            ],
            [
                'slug' => 'monsoon-health-checklist-for-city-readers',
                'author_id' => $priya->id,
                'category' => 'Health',
                'title' => 'Monsoon Health Checklist For City Readers',
                'image_url' => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Simple routines around hydration, food safety, and sleep are doing more than expensive wellness fixes.',
                'content' => 'Seasonal health advice works best when it is practical. Doctors and urban clinics are seeing the same pattern every year: readers who handle sleep, hydration, and food hygiene consistently avoid a big share of monsoon-related issues. This article turns that advice into a simple, usable checklist.',
                'price' => 32,
                'commission_type' => CommissionType::FIXED,
                'commission_value' => 5,
                'access_duration_hours' => 24,
                'view_count' => 1910,
                'unlock_count' => 289,
                'rating_average' => 4.5,
                'rating_count' => 119,
                'published_at' => now()->subHours(15),
            ],
            [
                'slug' => 'under-19-cricket-training-gets-a-data-driven-upgrade',
                'author_id' => $arjun->id,
                'category' => 'Sports',
                'title' => 'Under-19 Cricket Training Gets A Data-Driven Upgrade',
                'image_url' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Video feedback, recovery tracking, and skill dashboards are changing how younger players prepare.',
                'content' => 'Junior cricket programs are borrowing more techniques from elite systems. Coaches are blending repetition with data, using small performance dashboards to track progress without overwhelming players. The result is a more structured path from raw talent to match-ready performance.',
                'price' => 44,
                'commission_type' => CommissionType::PERCENTAGE,
                'commission_value' => 9,
                'access_duration_hours' => 24,
                'view_count' => 2230,
                'unlock_count' => 344,
                'rating_average' => 4.7,
                'rating_count' => 135,
                'published_at' => now()->subHours(19),
            ],
            [
                'slug' => 'classrooms-are-using-ai-tools-but-teachers-still-set-the-rules',
                'author_id' => $author->id,
                'category' => 'Education',
                'title' => 'Classrooms Are Using AI Tools, But Teachers Still Set The Rules',
                'image_url' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Schools are opening the door to AI, but with clear guardrails around trust, originality, and assessment.',
                'content' => 'The classroom story is not students versus AI. It is teachers designing better boundaries for how AI can support brainstorming, research, and revision without replacing effort. The schools seeing progress are the ones treating these tools as a skill to manage, not a shortcut to ignore.',
                'price' => 38,
                'commission_type' => CommissionType::FIXED,
                'commission_value' => 6,
                'access_duration_hours' => 72,
                'view_count' => 1780,
                'unlock_count' => 254,
                'rating_average' => 4.4,
                'rating_count' => 82,
                'published_at' => now()->subHours(25),
            ],
            [
                'slug' => 'space-clubs-in-schools-are-turning-science-into-a-weekend-habit',
                'author_id' => $arjun->id,
                'category' => 'Science',
                'title' => 'Space Clubs In Schools Are Turning Science Into A Weekend Habit',
                'image_url' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Telescope nights, model rockets, and maker labs are helping science feel less abstract for students.',
                'content' => 'The most successful school science clubs create momentum outside the timetable. By making room for curiosity-led projects, local mentors, and simple observation routines, they turn science from a textbook subject into a habit that students carry into the weekend.',
                'price' => 36,
                'commission_type' => CommissionType::PERCENTAGE,
                'commission_value' => 8,
                'access_duration_hours' => 48,
                'view_count' => 1640,
                'unlock_count' => 248,
                'rating_average' => 4.8,
                'rating_count' => 97,
                'published_at' => now()->subHours(31),
            ],
            [
                'slug' => 'young-creators-are-building-global-brands-from-small-indian-towns',
                'author_id' => $priya->id,
                'category' => 'World',
                'title' => 'Young Creators Are Building Global Brands From Small Indian Towns',
                'image_url' => 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Remote work, digital storefronts, and creator tools are expanding what global ambition looks like.',
                'content' => 'The internet has flattened more than distribution. It has changed where ambition can begin. Creators from smaller towns are using global platforms, local production networks, and niche communities to launch products that travel far beyond their geography.',
                'price' => 58,
                'commission_type' => CommissionType::PERCENTAGE,
                'commission_value' => 11,
                'access_duration_hours' => 24,
                'view_count' => 2090,
                'unlock_count' => 320,
                'rating_average' => 4.6,
                'rating_count' => 111,
                'published_at' => now()->subHours(38),
            ],
            [
                'slug' => 'why-short-explainer-journalism-is-winning-busy-readers',
                'author_id' => $arjun->id,
                'category' => 'Lifestyle',
                'title' => 'Why Short Explainer Journalism Is Winning Busy Readers',
                'image_url' => 'https://images.unsplash.com/photo-1516321165247-4aa89a48be28?auto=format&fit=crop&w=1200&q=80',
                'preview_text' => 'Well-structured explainers are outperforming noisy feeds because they respect both attention and curiosity.',
                'content' => 'Readers are not rejecting depth. They are rejecting friction. Short explainer journalism works because it creates fast orientation before inviting deeper reading. That pattern is shaping everything from newsletters and podcasts to premium article packaging.',
                'price' => 42,
                'commission_type' => CommissionType::FIXED,
                'commission_value' => 7,
                'access_duration_hours' => 24,
                'view_count' => 2460,
                'unlock_count' => 351,
                'rating_average' => 4.9,
                'rating_count' => 172,
                'published_at' => now()->subHours(46),
            ],
        ];

        foreach ($articles as $article) {
            Article::query()->updateOrCreate([
                'slug' => $article['slug'],
            ], [
                'author_id' => $article['author_id'],
                'approved_by' => $admin->id,
                'category' => $article['category'],
                'title' => $article['title'],
                'image_url' => $article['image_url'],
                'preview_text' => $article['preview_text'],
                'content' => $article['content'],
                'price' => $article['price'],
                'commission_type' => $article['commission_type'],
                'commission_value' => $article['commission_value'],
                'access_duration_hours' => $article['access_duration_hours'],
                'status' => ArticleStatus::PUBLISHED,
                'view_count' => $article['view_count'],
                'unlock_count' => $article['unlock_count'],
                'rating_average' => $article['rating_average'],
                'rating_count' => $article['rating_count'],
                'published_at' => $article['published_at'],
                'approved_at' => $article['published_at'],
            ]);
        }
    }
}
