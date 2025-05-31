<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use App\Models\Test;

class TestController extends Controller implements HasMiddleware
    {
        public static function middleware(): array
        {
            return [
                new Middleware('auth')
            ];
        }
    
    /**
     * @OA\Get(
     *     path="/test",
     *     tags={"Test"},
     *     summary="list all Tests",
     *     description="list all Tests",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             default="1"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success Message",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="current_page",
     *                 type="integer",
     *                 format="int32",
     *                 description="Current page number"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/TestModel"),
     *                 description="List of item"
     *             ),
     *             @OA\Property(
     *                 property="first_page_url",
     *                 type="string",
     *                 format="uri",
     *                 description="First page URL"
     *             ),
     *             @OA\Property(
     *                 property="from",
     *                 type="integer",
     *                 format="int32",
     *                 description="First item number in the current page"
     *             ),
     *             @OA\Property(
     *                 property="last_page",
     *                 type="integer",
     *                 format="int32",
     *                 description="Last page number"
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="array",
     *                 @OA\Items(
     *                     oneOf={
     *                         @OA\Schema(ref="#/components/schemas/Previous"),
     *                         @OA\Schema(ref="#/components/schemas/Links"),
     *                         @OA\Schema(ref="#/components/schemas/Next")
     *                     }
     *                 ),
     *                 description="Links"
     *             ),
     *             @OA\Property(
     *                 property="last_page_url",
     *                 type="string",
     *                 format="uri",
     *                 description="Last page URL"
     *             ),
     *             @OA\Property(
     *                 property="next_page_url",
     *                 type="string",
     *                 format="uri",
     *                 description="Next page URL"
     *             ),
     *             @OA\Property(
     *                 property="path",
     *                 type="string",
     *                 description="Path"
     *             ),
     *             @OA\Property(
     *                 property="per_page",
     *                 type="integer",
     *                 format="int32",
     *                 description="Items per page"
     *             )
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an ""unexpected"" error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     *
     * @return JsonResponse
     * Display the specified resource.
     */
    public function index(): JsonResponse
    {
        return $this->success((new Test())->latest()->paginate(20));
    }

    /**
     * @OA\Post(
     *     path="/test",
     *     tags={"Test"},
     *     summary="create Test",
     *     description="create Test",
     *     operationId="CreateTest",
     *     deprecated=false,
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @OA\JsonContent(ref="#/components/schemas/TestModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),
     *     @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="test",
     *                 description="test",
     *                 type="string",
     *                 format="",
     *                 example="string",
     *             ),
     *             @OA\Property(
     *                 property="something",
     *                 description="something",
     *                 type="string",
     *                 format="",
     *                 example="string",
     *             ),
     *         )
     *     ),security={{"api_key": {}}}
     * )
     *
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'test'      => 'required|string|max:255',
            'something' => 'required|string|max:255',
        ]);
        try {
            return $this->success((new Test())->create($request->all()));
        } catch (Exception $e) {
            //return error message
            Log::error($e->getMessage());
            return $this->error('create error');
        }
    }

    /**
     * @OA\Get(
     *     path="/test/{id}",
     *     tags={"Test"},
     *     summary="get one Test",
     *     description="one Test",
     *     operationId="getOneTest",
     *     deprecated=false,
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="success",
     *         @OA\JsonContent(ref="#/components/schemas/TestModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     *
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = (new Test())->findOrFail($id);
            return $this->success($result);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('nothing to show');
        }
    }

    /**
     * @OA\Put(
     *     path="/test/{id}",
     *     tags={"Test"},
     *     summary="update Test",
     *     description="update Test",
     *     operationId="UpdateTest",
     *     deprecated=false,
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @OA\JsonContent(ref="#/components/schemas/TestModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description="tasks input",
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="test",
     *                 description="test",
     *                 type="string",
     *                 format="",
     *                 example="string",
     *             ),
     *             @OA\Property(
     *                 property="something",
     *                 description="something",
     *                 type="string",
     *                 format="",
     *                 example="string",
     *             ),
     *         )
     *     ),security={{"api_key": {}}}
     * )
     *
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int     $id
     * @return JsonResponse
     * @throws ValidationException
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'test'      => 'required|string|max:255',
            'something' => 'required|string|max:255',
        ]);
        try {
            $result = (new Test())->findOrFail($id);
            $result->update($request->all());
            return $this->success($result);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('update error');
        }
    }

    /**
     * @OA\Delete(
     *     path="/test/{id}",
     *     tags={"Test"},
     *     summary="delete Test",
     *     description="delete Test",
     *     operationId="deleteTest",
     *     deprecated=false,
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Success Message",
     *         @OA\JsonContent(ref="#/components/schemas/SuccessModel"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="an unexpected error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorModel"),
     *     ),security={{"api_key": {}}}
     * )
     *
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = (new Test())->findOrFail($id);
            $result->delete();
            $id = $result->id;
            return $this->success("post $id deleted");
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->error('delete error');
        }
    }
}
