<?php

namespace App\Http\Controllers;

use App\Enums\DesignStatus;
use App\Enums\UserRole;
use App\Http\Requests\DesignRequest;
use App\Models\Category;
use App\Models\Design;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DesignController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Design::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'status' => ['nullable', Rule::enum(DesignStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Design::with('category');
        $isAdmin = $request->user()?->role === UserRole::Admin;
        if (! $isAdmin) {
            $query->where('status', DesignStatus::Published->value);
        }
        if (filled($filters['search'] ?? null)) {
            // Explicit escape character works on MySQL and SQLite alike.
            $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['search']));
            $query->whereRaw("title LIKE ? ESCAPE '!'", [$prefix.'%']);
        }
        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', $filters['category_id']);
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        return view('designs.index', [
            'designs' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(24)
                ->appends($request->only('search', 'category_id', 'status')),
            'categories' => Category::orderBy('name')->get(),
            'filters' => $filters,
            'availableStatuses' => $isAdmin ? DesignStatus::cases() : [DesignStatus::Published],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Design::class);

        return view('designs.form', ['design' => new Design, 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(DesignRequest $request): RedirectResponse
    {
        $design = $this->save($request, new Design);

        return redirect()->route('designs.show', $design)->with('status', 'Design created successfully.');
    }

    public function show(Design $design): View
    {
        Gate::authorize('view', $design);

        return view('designs.show', ['design' => $design->load('category')]);
    }

    public function edit(Design $design): View
    {
        Gate::authorize('update', $design);

        return view('designs.form', ['design' => $design, 'categories' => Category::orderBy('name')->get()]);
    }

    public function update(DesignRequest $request, Design $design): RedirectResponse
    {
        $this->save($request, $design);

        return redirect()->route('designs.show', $design)->with('status', 'Design updated successfully.');
    }

    public function destroy(Design $design): RedirectResponse
    {
        Gate::authorize('delete', $design);
        $thumbnail = $design->thumbnail;
        $design->delete();
        $this->deleteUpload($thumbnail);

        return redirect()->route('dashboard')->with('status', 'Design deleted successfully.');
    }

    private function save(DesignRequest $request, Design $design): Design
    {
        $old = $design->thumbnail;
        $upload = null;
        try {
            $attributes = $request->safe()->except('thumbnail');
            if ($request->hasFile('thumbnail')) {
                $upload = $request->file('thumbnail')->store('designs', 'public');
                if ($upload === false) {
                    throw new \RuntimeException('Unable to save the thumbnail.');
                }
                $attributes['thumbnail'] = $upload;
            } elseif (! $design->exists) {
                $attributes['thumbnail'] = 'placeholders/design.png';
            }
            DB::transaction(fn () => $design->fill($attributes)->save());
        } catch (\Throwable $exception) {
            $this->deleteUpload($upload ?: null);
            throw $exception;
        }
        if ($upload !== null) {
            $this->deleteUpload($old);
        }

        return $design;
    }

    private function deleteUpload(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'designs/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
