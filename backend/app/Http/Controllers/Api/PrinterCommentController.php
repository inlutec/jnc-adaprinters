<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use App\Models\PrinterComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrinterCommentController extends Controller
{
    public function index(Printer $printer): JsonResponse
    {
        $comments = $printer->comments()
            ->with('user:id,name')
            ->latest()
            ->get();

        return response()->json($comments);
    }

    public function store(Request $request, Printer $printer): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $comment = PrinterComment::create([
            'printer_id' => $printer->id,
            'user_id' => $request->user()?->id,
            'body' => trim($validated['body']),
        ]);

        $comment->load('user:id,name');

        return response()->json($comment, 201);
    }
}


