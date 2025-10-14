<?php

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Atlas API",
 *     version="1.0.0",
 *     description="API documentation for the Atlas backend."
 * )
 *
 * @OA\Server(
 *     url="http://localhost",
 *     description="Local development server"
 * )
 */
class Info
{
}
