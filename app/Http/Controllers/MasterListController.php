<?php

namespace App\Http\Controllers;

use App\Actions\Lists\EnsureMasterList;
use App\Http\Resources\TodoListResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MasterListController extends Controller
{
    public function __invoke(Request $request, EnsureMasterList $ensureMasterList): Response
    {
        $user = $request->user();
        $list = $ensureMasterList($user);

        return Inertia::render('lists/Master', [
            'list' => TodoListResource::make($list->load('todos.tags'))->resolve(),
        ]);
    }
}
