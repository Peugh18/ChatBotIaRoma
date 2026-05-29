<?php

namespace App\Services;

use App\Models\Category;
use App\Models\ConversationState;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Catálogo: categoría → estilo (tags_ia) → productos. Todo desde BD.
 */
class CategoryBrowseService
{
    public function __construct(
        protected ToolExecutorService $tools,
        protected BusinessConfigService $business,
        protected ProductPresentationService $presentation,
        protected ProductMediaService $media,
    ) {}

    /**
     * Etapa 3: primero categoría (no todo el catálogo).
     */
    public function presentCategorySelection(ConversationState $state): array
    {
        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q->whereHas('variants'))
            ->withCount(['products' => fn ($q) => $q->whereHas('variants')])
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return $this->presentProductCatalog($state, skipCategoryGate: true);
        }

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_category_selection';
        unset($ctx['current_category_id'], $ctx['current_category_name'], $ctx['current_style_filter']);
        $state->context = $ctx;
        $state->save();

        $lines = [
            $this->business->welcomeMessage(),
            '',
            '¿Qué tipo de prenda buscas?',
            '',
        ];
        foreach ($categories as $i => $cat) {
            $lines[] = ($i + 1).'. '.$cat->name;
        }
        $lines[] = '';
        $lines[] = 'Toca una opción o escribe el nombre de la categoría ✨';

        $text = implode("\n", $lines);

        if ($categories->count() <= 3) {
            $buttons = [];
            foreach ($categories as $cat) {
                $buttons[] = [
                    'id' => 'pick_category_'.$cat->id,
                    'title' => mb_substr($cat->name, 0, 20),
                ];
            }
            $buttons[] = ['id' => 'show_all_products', 'title' => 'Ver todo'];
            $this->tools->executeSendInteractiveButtons($state, $text, array_slice($buttons, 0, 3), 'Tipo de prenda');
        } else {
            $rows = [];
            foreach ($categories->take(9) as $cat) {
                $rows[] = [
                    'id' => 'pick_category_'.$cat->id,
                    'title' => mb_substr($cat->name, 0, 24),
                    'description' => $cat->products_count.' modelos',
                ];
            }
            $rows[] = [
                'id' => 'show_all_products',
                'title' => 'Ver catálogo completo',
                'description' => 'Todos los modelos',
            ];
            $this->tools->executeSendInteractiveList(
                $state,
                $text,
                'Ver categorías',
                [['title' => 'Prendas', 'rows' => $rows]],
                'Elige categoría'
            );
        }

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    /**
     * Lista de productos (tras categoría/estilo o "ver todo").
     */
    public function presentProductCatalog(ConversationState $state, bool $skipCategoryGate = false): array
    {
        if (! $skipCategoryGate && ! ($state->context['current_category_id'] ?? null) && ! ($state->context['current_style_filter'] ?? null)) {
            return $this->presentCategorySelection($state);
        }

        $query = $this->querySellableProducts();
        if ($categoryId = (int) ($state->context['current_category_id'] ?? 0)) {
            $query->where('category_id', $categoryId);
        }
        if ($style = (string) ($state->context['current_style_filter'] ?? '')) {
            $query->whereJsonContains('tags_ia', $style);
        }

        $products = $query->limit(10)->get();

        if ($products->isEmpty()) {
            return [
                'text' => $this->business->applyBrandCta(
                    'No hay modelos con ese filtro ahora 🙏 Escríbeme el nombre del vestido o mándame una foto 📸'
                ),
                'metadata' => [],
            ];
        }

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_product_selection';
        $state->context = $ctx;
        $state->save();

        $categoryName = $state->context['current_category_name'] ?? 'Catálogo';

        return $this->presentProductList($state, $products, [
            'title' => "{$categoryName} 👗",
            'subtitle' => 'Modelos disponibles:',
            'footer' => 'Toca un vestido para ver colores, tallas y foto 📸',
            'interactive_footer' => 'Elige vestido',
            'list_button' => 'Ver modelos',
            'section_title' => $categoryName,
        ]);
    }

    public function presentStyleSelection(ConversationState $state, int $categoryId): ?array
    {
        $styles = $this->availableStylesForCategory($categoryId);
        if (count($styles) < 2) {
            return null;
        }

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_style_selection';
        $ctx['current_category_id'] = $categoryId;
        $state->context = $ctx;
        $state->save();

        $lines = ['¿Qué estilo buscas? ✨', ''];
        $i = 1;
        foreach ($styles as $key => $label) {
            $lines[] = $i.'. '.$label;
            $i++;
        }

        $text = implode("\n", $lines);
        $buttons = [];
        foreach (array_slice($styles, 0, 2, true) as $key => $label) {
            $buttons[] = ['id' => 'pick_style_'.$key, 'title' => mb_substr($label, 0, 20)];
        }
        $buttons[] = ['id' => 'skip_style_filter', 'title' => 'Ver todos'];
        $this->tools->executeSendInteractiveButtons($state, $text, $buttons, 'Estilo');

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    /**
     * Menú de filtros por categoría (opcional, no es el paso de venta).
     */
    public function presentCategoryFilterMenu(ConversationState $state): array
    {
        return $this->presentCategorySelection($state);
    }

    /** @deprecated Use presentCategorySelection */
    public function presentCategoryMenu(ConversationState $state): array
    {
        return $this->presentCategorySelection($state);
    }

    public function presentCategoryProducts(ConversationState $state, int $categoryId): array
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return $this->presentCategorySelection($state);
        }

        $ctx = $state->context ?? [];
        $ctx['current_category_id'] = $categoryId;
        $ctx['current_category_name'] = $category->name;
        $state->context = $ctx;
        $state->save();

        $styleMenu = $this->presentStyleSelection($state, $categoryId);
        if ($styleMenu !== null) {
            return $styleMenu;
        }

        return $this->presentProductCatalog($state, skipCategoryGate: true);
    }

    public function handleStyleSelection(ConversationState $state, string $message): ?array
    {
        if (($state->context['sales_stage'] ?? null) !== 'awaiting_style_selection') {
            return null;
        }

        $trimmed = trim($message);
        if ($trimmed === 'skip_style_filter' || preg_match('/\b(ver todos|todos|sin filtro)\b/iu', $trimmed)) {
            $ctx = $state->context ?? [];
            unset($ctx['current_style_filter']);
            $ctx['sales_stage'] = 'awaiting_product_selection';
            $state->context = $ctx;
            $state->save();

            return $this->presentProductCatalog($state, skipCategoryGate: true);
        }

        if (preg_match('/^pick_style_([a-z0-9_]+)$/i', $trimmed, $m)) {
            $ctx = $state->context ?? [];
            $ctx['current_style_filter'] = $m[1];
            $ctx['sales_stage'] = 'awaiting_product_selection';
            $state->context = $ctx;
            $state->save();

            return $this->presentProductCatalog($state, skipCategoryGate: true);
        }

        $filters = config('sales_flow.style_filters', []);
        $needle = mb_strtolower($trimmed);
        foreach ($filters as $key => $label) {
            if ($needle === mb_strtolower($key) || $needle === mb_strtolower($label)) {
                $ctx = $state->context ?? [];
                $ctx['current_style_filter'] = $key;
                $ctx['sales_stage'] = 'awaiting_product_selection';
                $state->context = $ctx;
                $state->save();

                return $this->presentProductCatalog($state, skipCategoryGate: true);
            }
        }

        return null;
    }

    public function handleCategorySelection(ConversationState $state, string $message): ?array
    {
        $trimmed = trim($message);
        if ($trimmed === '' || mb_strtolower($trimmed) === 'show_all_products') {
            return $this->presentProductCatalog($state);
        }

        if (preg_match('/^pick_category_(\d+)$/i', $trimmed, $m)) {
            return $this->presentCategoryProducts($state, (int) $m[1]);
        }

        if (preg_match('/^(ver todo|todo|catalogo|catálogo|todos)$/iu', $trimmed)) {
            return $this->presentProductCatalog($state);
        }

        $category = $this->findCategoryByName($trimmed);
        if ($category) {
            return $this->presentCategoryProducts($state, $category->id);
        }

        $stage = $state->context['sales_stage'] ?? null;
        if (! in_array($stage, ['awaiting_category_selection', 'awaiting_category_filter', 'awaiting_style_selection'], true)) {
            return null;
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            $index = (int) $trimmed - 1;
            $categories = Category::query()
                ->whereHas('products', fn ($q) => $q->whereHas('variants'))
                ->orderBy('name')
                ->get();
            if (isset($categories[$index])) {
                return $this->presentCategoryProducts($state, $categories[$index]->id);
            }
        }

        if (preg_match('/categor[ií]a\s*(\d+)/iu', $trimmed, $m)) {
            return $this->presentCategoryProducts($state, (int) $m[1]);
        }

        return null;
    }

    public function handleProductSelection(ConversationState $state, string $message): ?array
    {
        $stage = $state->context['sales_stage'] ?? null;
        if ($stage !== 'awaiting_product_selection') {
            return null;
        }

        $trimmed = trim($message);

        if (preg_match('/^pick_product_(\d+)$/i', $trimmed, $m)) {
            return $this->presentation->presentProductPick($state, (int) $m[1]);
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            $index = (int) $trimmed - 1;
            $shown = $state->context['last_shown_products'] ?? [];
            if (isset($shown[$index]['id'])) {
                return $this->presentation->presentProductPick($state, (int) $shown[$index]['id']);
            }
        }

        $category = $this->findCategoryByName($trimmed);
        if ($category) {
            return $this->presentCategoryProducts($state, $category->id);
        }

        $product = $this->findProductByNameInContext($state, $trimmed);
        if ($product) {
            return $this->presentation->presentProductPick($state, $product->id);
        }

        $search = $this->tools->executeGetProducts($state, $trimmed, null, false);
        if (($search['count'] ?? 0) === 1) {
            return $this->presentation->presentProductPick($state, (int) $search['products'][0]['id']);
        }
        if (($search['count'] ?? 0) > 1) {
            return $this->presentationFromProductSearch($state, $search);
        }

        $categoryId = (int) ($state->context['current_category_id'] ?? 0);
        if ($categoryId > 0) {
            return $this->presentCategoryProducts($state, $categoryId);
        }

        return $this->presentProductCatalog($state);
    }

    public function findCategoryByName(string $name): ?Category
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '' || mb_strlen($needle) < 2) {
            return null;
        }

        $categories = Category::query()
            ->whereHas('products', fn ($q) => $q->whereHas('variants'))
            ->get();

        foreach ($categories as $cat) {
            if (mb_strtolower($cat->name) === $needle) {
                return $cat;
            }
        }

        foreach ($categories as $cat) {
            if (str_contains(mb_strtolower($cat->name), $needle) || str_contains($needle, mb_strtolower($cat->name))) {
                return $cat;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $searchResult
     */
    public function presentationFromProductSearch(ConversationState $state, array $searchResult): array
    {
        $count = (int) ($searchResult['count'] ?? 0);
        if ($count === 1) {
            return $this->presentation->presentProductPick($state, (int) $searchResult['products'][0]['id']);
        }

        $ctx = $state->context ?? [];
        $ctx['sales_stage'] = 'awaiting_product_selection';
        $ctx['last_shown_products'] = array_map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'final_price' => $p['final_price'],
        ], array_slice($searchResult['products'], 0, 8));
        $state->context = $ctx;
        $state->save();

        $ids = array_column($ctx['last_shown_products'], 'id');
        $products = Product::with(['variants', 'images'])->whereIn('id', $ids)->get()
            ->sortBy(fn ($p) => array_search($p->id, $ids, true));

        return $this->presentProductList($state, $products, [
            'title' => 'Encontré estas opciones para ti ✨',
            'subtitle' => '',
            'footer' => 'Toca un vestido para ver colores y foto 📸',
            'interactive_footer' => 'Elige vestido',
            'list_button' => 'Ver opciones',
            'section_title' => 'Resultados',
        ]);
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<string, string>  $copy
     */
    protected function presentProductList(ConversationState $state, Collection $products, array $copy): array
    {
        $productRows = [];
        $lines = array_filter([$copy['title'] ?? '', $copy['subtitle'] ?? '', '']);

        $shownProducts = [];
        $index = 0;

        foreach ($products as $product) {
            $validation = PriceValidatorService::validateProductPrice($product);
            if (! $validation['valid']) {
                continue;
            }
            $price = number_format((float) $validation['final_price'], 2);
            $num = $index + 1;
            $lines[] = $num.'. '.$product->name.' — S/ '.$price;
            $productRows[] = [
                'id' => 'pick_product_'.$product->id,
                'title' => mb_substr($product->name, 0, 24),
                'description' => 'S/ '.$price,
            ];
            $shownProducts[] = [
                'id' => $product->id,
                'name' => $product->name,
                'final_price' => (float) $validation['final_price'],
            ];
            $index++;
        }

        if (empty($productRows)) {
            return $this->presentProductCatalog($state);
        }

        if (! empty($copy['footer'])) {
            $lines[] = '';
            $lines[] = $copy['footer'];
        }

        $text = implode("\n", $lines);

        $ctx = $state->context ?? [];
        $ctx['last_shown_products'] = $shownProducts;
        $state->context = $ctx;
        $state->save();

        if (count($productRows) <= 3) {
            $buttons = array_map(fn ($r) => ['id' => $r['id'], 'title' => $r['title']], $productRows);
            $this->tools->executeSendInteractiveButtons(
                $state,
                $text,
                $buttons,
                $copy['interactive_footer'] ?? 'Elige vestido'
            );
        } else {
            $this->tools->executeSendInteractiveList(
                $state,
                $text,
                $copy['list_button'] ?? 'Ver vestidos',
                [['title' => $copy['section_title'] ?? 'Vestidos', 'rows' => array_slice($productRows, 0, 10)]],
                'Toca para ver colores'
            );
        }

        $first = $products->first();
        if ($first) {
            $first->loadMissing('variants');
            $variant = $first->variants->first();
            $previewUrl = $variant ? $this->media->resolvePublicUrl($variant) : null;
            if ($previewUrl && $this->media->isUrlReachableByMeta($previewUrl)) {
                $this->tools->executeSendProductImage($state, $first->id, null);
            }
        }

        return ['text' => $this->business->applyBrandCta($text), 'metadata' => []];
    }

    protected function findProductByNameInContext(ConversationState $state, string $name): ?Product
    {
        $needle = mb_strtolower(trim($name));
        if ($needle === '') {
            return null;
        }

        foreach ($state->context['last_shown_products'] ?? [] as $row) {
            if (mb_strtolower((string) ($row['name'] ?? '')) === $needle) {
                return Product::find($row['id']);
            }
        }

        foreach ($state->context['last_shown_products'] ?? [] as $row) {
            $productName = mb_strtolower((string) ($row['name'] ?? ''));
            if (str_contains($productName, $needle) || str_contains($needle, $productName)) {
                return Product::find($row['id']);
            }
        }

        return Product::query()
            ->whereHas('variants')
            ->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(name) = ?', [$needle])
                    ->orWhere('name', 'like', '%'.$needle.'%');
            })
            ->first();
    }

    protected function querySellableProducts()
    {
        return Product::with(['variants', 'images', 'category'])
            ->whereHas('variants')
            ->orderByDesc('updated_at');
    }

    /**
     * @return array<string, string> slug => etiqueta
     */
    protected function availableStylesForCategory(int $categoryId): array
    {
        $configured = config('sales_flow.style_filters', []);
        $tagsInCategory = Product::query()
            ->where('category_id', $categoryId)
            ->whereHas('variants')
            ->pluck('tags_ia')
            ->filter()
            ->flatMap(fn ($tags) => is_array($tags) ? $tags : [])
            ->map(fn ($t) => mb_strtolower((string) $t))
            ->unique()
            ->values();

        $available = [];
        foreach ($configured as $key => $label) {
            if ($tagsInCategory->contains(mb_strtolower($key))) {
                $available[$key] = $label;
            }
        }

        return $available;
    }
}
