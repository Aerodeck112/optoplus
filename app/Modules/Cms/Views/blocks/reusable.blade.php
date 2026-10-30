@php($rb = \App\Modules\Cms\Models\ReusableBlock::find($d['block_id'] ?? 0))
@if ($rb && \App\Modules\Cms\Services\BlockRegistry::exists($rb->type) && $rb->type !== 'reusable')
    @include('cms::blocks.'.$rb->type, ['d' => $rb->data, 'index' => $index, 'media' => $media + \App\Modules\Cms\Services\PageRenderer::mediaFor([['type' => $rb->type, 'data' => $rb->data]]), 'first' => $first])
@endif
