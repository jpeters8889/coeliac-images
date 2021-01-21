<?php

namespace Coeliac\Images;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

/**
 * @mixin Filesystem
 */
class FilesystemManager
{
    protected Filesystem $filesystem;

    public function __construct()
    {
        $this->bootstrap();
    }

    protected function bootstrap()
    {
        $client = new S3Client([
            'region' => env('AWS_DEFAULT_REGION', 'eu-west-2'),
            'version' => 'latest',
        ]);

        $adapter = new AwsS3V3Adapter($client, env('AWS_BUCKET', 'coeliac-images'));

        $this->filesystem = new Filesystem($adapter);
    }

    public function __call($method, $params)
    {
        if (method_exists($this->filesystem, $method)) {
            return $this->filesystem->$method(...$params);
        }

        throw new \BadMethodCallException("Unknown method: {$method}.");
    }
}
