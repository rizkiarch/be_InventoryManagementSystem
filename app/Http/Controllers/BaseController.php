<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Permission;
use App\Models\Role;

class BaseController extends Controller
{
    /**
     * Check if the authenticated user has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    protected function hasPermission($permission)
    {
        return Auth::user()->hasPermissionTo($permission);
    }

    /**
     * Check if the authenticated user has any of the given permissions.
     *
     * @param array $permissions
     * @return bool
     */
    protected function hasAnyPermission(array $permissions)
    {
        return Auth::user()->hasAnyPermission($permissions);
    }

    /**
     * Check if the authenticated user has a specific role.
     *
     * @param string $role
     * @return bool
     */
    protected function hasRole($role)
    {
        return Auth::user()->hasRole($role);
    }

    /**
     * Check if the authenticated user has any of the given roles.
     *
     * @param array $roles
     * @return bool
     */
    protected function hasAnyRole(array $roles)
    {
        return Auth::user()->hasAnyRole($roles);
    }

    /**
     * Send a success response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function successResponse($data, $message = 'Success', $statusCode = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Send an error response.
     *
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse($message = "Ups Ada Error", $error,  $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $error,
        ], $statusCode);
    }

    /**
     * Send a not found response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFoundResponse($message = 'Resource not found')
    {
        return $this->errorResponse($message, 404);
    }
}
