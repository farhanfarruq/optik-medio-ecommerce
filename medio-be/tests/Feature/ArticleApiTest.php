<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ArticleApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('articles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('featured_image')->nullable();
            $table->json('tags')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_article_detail_only_returns_published_articles(): void
    {
        $draft = $this->createArticle([
            'slug' => 'draft-article',
            'is_published' => false,
            'published_at' => now()->subDay(),
        ]);

        $future = $this->createArticle([
            'slug' => 'future-article',
            'is_published' => true,
            'published_at' => now()->addDay(),
        ]);

        $this->getJson("/api/articles/{$draft->slug}")->assertNotFound();
        $this->getJson("/api/articles/{$future->slug}")->assertNotFound();
    }

    public function test_article_detail_sanitizes_content_html(): void
    {
        $article = $this->createArticle([
            'slug' => 'safe-content',
            'content' => '<h2>Heading</h2><p>Body</p><script>alert(1)</script><img src="x" onerror="alert(1)">',
        ]);

        $content = $this->getJson("/api/articles/{$article->slug}")
            ->assertOk()
            ->json('article.content');

        $this->assertStringContainsString('<h2>Heading</h2>', $content);
        $this->assertStringContainsString('<p>Body</p>', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
    }

    public function test_article_search_escapes_like_wildcards(): void
    {
        $this->createArticle([
            'title' => '100% Vision Guide',
            'slug' => 'percent-guide',
            'excerpt' => 'Literal percent sign.',
        ]);
        $this->createArticle([
            'title' => 'Plain Vision Guide',
            'slug' => 'plain-guide',
            'excerpt' => 'No wildcard marker.',
        ]);

        $slugs = collect($this->getJson('/api/articles?search=%')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();

        $this->assertSame(['percent-guide'], $slugs);
    }

    public function test_article_search_does_not_leak_unpublished_articles(): void
    {
        $this->createArticle([
            'title' => 'Public keyword',
            'slug' => 'public-keyword',
            'excerpt' => 'Visible content.',
        ]);
        $this->createArticle([
            'title' => 'Secret keyword',
            'slug' => 'secret-keyword',
            'excerpt' => 'Hidden content.',
            'is_published' => false,
        ]);

        $slugs = collect($this->getJson('/api/articles?search=keyword')
            ->assertOk()
            ->json('data'))
            ->pluck('slug')
            ->all();

        $this->assertSame(['public-keyword'], $slugs);
    }

    private function createArticle(array $overrides = []): Article
    {
        static $index = 0;
        $index++;

        return Article::create(array_merge([
            'author_id' => User::factory()->create()->id,
            'title' => "Article {$index}",
            'slug' => "article-{$index}",
            'excerpt' => "Excerpt {$index}",
            'content' => '<p>Content</p>',
            'tags' => ['tips'],
            'is_published' => true,
            'published_at' => now()->subDay(),
            'views' => 0,
        ], $overrides));
    }
}
