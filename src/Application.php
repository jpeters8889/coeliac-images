<?php

namespace Coeliac\Images;

use Dotenv\Dotenv;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Env;

class Application
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function bootstrap()
    {
        $this->registerEnvironment();
    }

    protected function registerEnvironment()
    {
        Dotenv::create(
            Env::getRepository(),
            $this->basePath,
            '.env',
        )->safeLoad();
    }

    public function handleRequest(Request $request): Response
    {
        try {
            $response = ImageController::handle($request);
        }
        catch(Exception $exception) {
            $response = $this->renderException($exception);
        }

        return $response;
    }

    protected function renderException(Exception $exception): Response
    {
        if(env('APP_ENV', 'local') !== 'local') {
            return new Response('', 404);
        }

        return new Response([
            'exception' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], 500);
    }
}
