<?php

namespace App\Domain\Images\Services;

use App\Domain\Images\Models\Image;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImageService
{
    /**
     * Resolve the configured type definition for an API-facing alias.
     *
     * @return array{model: class-string<Model>, permission: string}
     */
    public function type(string $alias): array
    {
        $type = config('images.types.'.$alias);

        if (! is_array($type)) {
            throw (new ModelNotFoundException)->setModel(Model::class, [$alias]);
        }

        return $type;
    }

    public function resolveParent(string $alias, int $id): Model
    {
        $type = $this->type($alias);
        $model = $type['model'];

        $parent = $model::query()->find($id);

        if (! $parent) {
            throw (new ModelNotFoundException)->setModel($model, [$id]);
        }

        return $parent;
    }

    public function permissionFor(Model $parent): string
    {
        foreach (config('images.types', []) as $type) {
            if ($parent instanceof $type['model']) {
                return $type['permission'];
            }
        }

        throw new RuntimeException('No image permission is configured for '.$parent::class.'.');
    }

    public function aliasFor(Model $parent): string
    {
        foreach (config('images.types', []) as $alias => $type) {
            if ($parent instanceof $type['model']) {
                return $alias;
            }
        }

        throw new RuntimeException('No image type is configured for '.$parent::class.'.');
    }

    public function attach(Model $parent, UploadedFile $file, bool $isPrimary, ?User $uploader): Image
    {
        $path = $file->store(
            'images/'.$this->aliasFor($parent).'/'.$parent->getKey(),
            config('images.disk', 'public')
        );

        if (! $path) {
            throw new RuntimeException('The image could not be stored.');
        }

        try {
            return DB::transaction(function () use ($parent, $file, $isPrimary, $uploader, $path) {
                $makePrimary = $isPrimary || ! $parent->images()->exists();

                if ($makePrimary) {
                    Image::query()
                        ->where('imageable_type', $parent->getMorphClass())
                        ->where('imageable_id', $parent->getKey())
                        ->update(['is_primary' => false]);
                }

                return $parent->images()->create([
                    'path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'is_primary' => $makePrimary,
                    'uploaded_by' => $uploader?->getKey(),
                ]);
            });
        } catch (Throwable $e) {
            Storage::disk(config('images.disk', 'public'))->delete($path);

            throw $e;
        }
    }

    /**
     * Keep exactly one primary image whenever a record still has images.
     */
    public function setPrimary(Image $image, bool $isPrimary): Image
    {
        return DB::transaction(function () use ($image, $isPrimary) {
            $siblings = $image->newQuery()
                ->where('imageable_type', $image->imageable_type)
                ->where('imageable_id', $image->imageable_id)
                ->whereKeyNot($image->getKey());

            if ($isPrimary) {
                $siblings->update(['is_primary' => false]);
                $image->update(['is_primary' => true]);

                return $image->fresh();
            }

            $replacement = $siblings->orderBy('id')->first();

            if ($replacement) {
                $replacement->update(['is_primary' => true]);
                $image->update(['is_primary' => false]);
            }

            return $image->fresh();
        });
    }

    public function delete(Image $image): void
    {
        $parent = $image->imageable;
        $wasPrimary = $image->is_primary;
        $path = $image->path;
        $disk = config('images.disk', 'public');

        DB::transaction(function () use ($image, $parent, $wasPrimary) {
            $image->delete();

            if ($wasPrimary && $parent) {
                $parent->images()->first()?->update(['is_primary' => true]);
            }
        });

        Storage::disk($disk)->delete($path);
    }
}
