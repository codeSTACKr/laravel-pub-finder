<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ResponseService;

class SearchController extends Controller
{
    protected $responseService;

    public function __construct(ResponseService $responseService)
    {
        $this->responseService = $responseService;
    }

    public function index()
    {
        return view('pubfinder');
    }

    public function perform(Request $request)
    {
        $query = $request->input('query');

        if (empty(trim($query))) {
            return back()->withErrors(['error' => 'Please enter a search query.']);
        }

        try {
            $results = $this->responseService->search($query);
        } catch (\Exception $e) {
            
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return view('pubfinder', compact('results', 'query'));
    }
}
