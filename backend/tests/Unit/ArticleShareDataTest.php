<?php

namespace Tests\Unit;

use App\Models\Article;
use App\Models\User;
use Tests\TestCase;

class ArticleShareDataTest extends TestCase
{
    public function test_share_fields_are_normalized_before_rendering(): void
    {
        $article = new Article;
        $article->setRawAttributes([
            'title' => ['Breaking', 'Update'],
            'preview_text' => ['Short', ['preview', 'copy']],
            'category' => null,
            'image_url' => ['https://example.com/story.jpg'],
        ], true);

        $author = new User;
        $author->setRawAttributes([
            'name' => ['Guest', 'Writer'],
        ], true);

        $article->setRelation('author', $author);

        $this->assertSame('Breaking Update', $article->shareTitle());
        $this->assertSame('Short preview copy', $article->sharePreviewText());
        $this->assertSame('General', $article->shareCategory());
        $this->assertSame('Guest Writer', $article->shareAuthorName());
        $this->assertSame('https://example.com/story.jpg', $article->shareImageUrl());
    }

    public function test_relative_share_image_paths_are_promoted_to_absolute_urls(): void
    {
        $article = new Article;
        $article->setRawAttributes([
            'image_url' => '/uploads/article-images/story.jpg',
        ], true);

        $this->assertSame(url('uploads/article-images/story.jpg'), $article->shareImageUrl());
    }
}
