<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    /**
     * Daftar artikel yang sudah dipublish (untuk frontend)
     */
    public function index(Request $request): JsonResponse
    {
        $articles = Article::published()
            ->select(['id', 'title', 'slug', 'excerpt', 'featured_image', 'tags', 'views', 'published_at'])
            ->when($request->tag, fn ($q) => $q->whereJsonContains('tags', $request->tag))
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%")
                ->orWhere('excerpt', 'like', "%{$request->search}%"))
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

        return response()->json([
            'article' => $article,
            'related' => $related,
        ]);
    }
}
