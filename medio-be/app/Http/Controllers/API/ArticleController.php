<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ArticleController extends Controller
{
    /**
     * Daftar artikel yang sudah dipublish (untuk frontend)
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim(substr((string) $request->query('search', ''), 0, 80));
        $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        $articles = Article::published()
            ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'tags', 'views', 'published_at'])
            ->when($request->tag, fn ($q) => $q->whereJsonContains('tags', $request->tag))
            ->when($search !== '', fn ($q) => $q->where(function ($query) use ($escapedSearch) {
                $pattern = "%{$escapedSearch}%";

                $query->whereRaw("title like ? escape '\\'", [$pattern])
                    ->orWhereRaw("excerpt like ? escape '\\'", [$pattern]);
            }))
            ->orderByDesc('published_at')
            ->paginate(12);

        return response()->json($articles);
    }

    /**
     * Detail artikel by slug (frontend)
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::with('author:id,name')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        // Increment views
        $article->increment('views');

        // Artikel terkait berdasarkan tags
        $related = [];
        if ($article->tags) {
            $related = Article::published()
                ->where('id', '!=', $article->id)
                ->where(function ($q) use ($article) {
                    $tags = is_array($article->tags) ? $article->tags : [];
                    foreach ($tags as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                })
                ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'published_at'])
                ->limit(3)
                ->get();
        }

        $article->content = $this->sanitizeArticleHtml($article->content);

        return response()->json([
            'article' => $article,
            'related' => $related,
        ]);
    }

    private function sanitizeArticleHtml(string $html): string
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias();

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
