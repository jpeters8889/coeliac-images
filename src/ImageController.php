<?php

namespace Coeliac\Images;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;

class ImageController
{
    protected Request $request;
    protected FilesystemManager $filesystem;
    protected string $path;
    protected string $fileExtension;
    protected string $rootDirectory;
    protected string $fileName;
    protected Image $image;

    public static function handle(Request $request): Response
    {
        return (new self($request))->response();
    }

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->filesystem = new FilesystemManager();
    }

    protected function cachedFileName(int $width): string
    {
        return "{$this->rootDirectory}/{$this->fileName}/{$width}.{$this->fileExtension}";
    }

    protected function fileDoesntExist(): bool
    {
        return !$this->filesystem->fileExists($this->path);
    }

    protected function findFileExtension(): void
    {
        $this->fileExtension = Arr::last(explode('.', $this->path));
    }

    protected function findFilePath(): void
    {
        $this->path = $this->request->path();
    }

    protected function findFileRootDirectory(): void
    {
        $parts = explode('/', $this->path);

        $this->fileName = Arr::first(explode('.', array_pop($parts)));
        $this->rootDirectory = implode('/', $parts);
    }

    protected function getCachedFile(int $width): void
    {
        $this->image = (new ImageManager())->make($this->filesystem->read($this->cachedFileName($width)));
    }

    protected function getFileFromS3(): string
    {
        return $this->filesystem->read($this->path);
    }

    protected function getResizeWidth(): int
    {
        $size = (int)$this->request->query('size', 1500);

        if ($size > 1500) {
            $size = 1500;
        }

        if ($size < 200) {
            $size = 200;
        }

        return ceil($size / 100) * 100;
    }

    protected function makeImageResource(string $rawImage): void
    {
        $this->image = (new ImageManager())->make($rawImage);
    }

    protected function prepareResponse(): Response
    {
        $response = new Response();
        $response->setContent($this->image->stream($this->fileExtension, '80'));
        $response->header('Content-Type', $this->filesystem->mimeType($this->path));

        return $response;
    }

    protected function resizedFileIsCached(int $width): bool
    {
        return $this->filesystem->fileExists($this->cachedFileName($width));
    }

    protected function resizeImage(): void
    {
        if ($this->request->query('size')) {
            $width = $this->getResizeWidth();

            if ($this->resizedFileIsCached($width)) {
                $this->getCachedFile($width);
                return;
            }

            $this->image->resize(
                $width,
                null,
                fn($constraint) => $constraint->aspectRatio()
            );

            $this->storeFileInCache($width);
        }
    }

    public function response(): Response
    {
        $this->findFilePath();
        $this->findFileExtension();
        $this->findFileRootDirectory();

        if ($this->fileDoesntExist()) {
            throw new Exception('File not found');
        }

        $rawImage = $this->getFileFromS3();
        $this->makeImageResource($rawImage);

        $this->resizeImage();

        return $this->prepareResponse();
    }

    protected function storeFileInCache(int $width): void
    {
        $this->filesystem->write($this->cachedFileName($width), $this->image->stream());
    }
}
