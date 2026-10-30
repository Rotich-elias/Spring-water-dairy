<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Images\Models\Image;
use App\Domain\Images\Services\ImageService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImageStoreRequest;
use App\Http\Requests\ImageUpdateRequest;
use App\Http\Resources\ImageResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ImagesController extends Controller
{
    public function __construct(private ImageService $images) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'imageable_type' => ['required', 'string', Rule::in(array_keys(config('images.types', [])))],
            'imageable_id' => ['required', 'integer', 'min:1'],
        ]);

        $parent = $this->images->resolveParent($data['imageable_type'], (int) $data['imageable_id']);

        Gate::authorize($this->images->permissionFor($parent).'.view', $parent);

        return response()->json([
            'data' => ImageResource::collection($parent->images()->get()),
        ]);
    }

    public function store(ImageStoreRequest $request)
    {
        $parent = $this->images->resolveParent(
            $request->validated('imageable_type'),
            (int) $request->validated('imageable_id')
        );

        Gate::authorize($this->images->permissionFor($parent).'.update', $parent);

        $image = $this->images->attach(
            $parent,
            $request->file('image'),
            $request->boolean('is_primary'),
            $request->user()
        );

        return response()->json(['data' => new ImageResource($image)], 201);
    }

    public function update(ImageUpdateRequest $request, Image $image)
    {
        $parent = $this->parentFor($image);

        Gate::authorize($this->images->permissionFor($parent).'.update', $parent);

        $image = $this->images->setPrimary($image, $request->boolean('is_primary'));

        return response()->json(['data' => new ImageResource($image)]);
    }

    public function destroy(Image $image)
    {
        $parent = $this->parentFor($image);

        Gate::authorize($this->images->permissionFor($parent).'.update', $parent);

        $this->images->delete($image);

        return response()->json(null, 204);
    }

    private function parentFor(Image $image): Model
    {
        $parent = $image->imageable;

        if (! $parent) {
            throw (new ModelNotFoundException)->setModel(Image::class, [$image->getKey()]);
        }

        return $parent;
    }
}
