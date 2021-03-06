<?php

namespace App\Http\Controllers;

use App\Filters\ThreadsFilters;
use App\Thread;
use App\Trending;
use Illuminate\Http\Request;
use App\Channel;
use Illuminate\Support\Facades\Redis;

class ThreadController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth')->except(['index', 'show']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Channel $channel, ThreadsFilters $filters)
    {

        $threads = $this->getThreads($channel, $filters);

        if (\request()->wantsJson()){
            return $threads;
        }

        $trending = (new Trending())->get();

        return view('threads.index', compact('threads', 'trending'));
    }

    protected function getThreads(Channel $channel, ThreadsFilters $filters)
    {
        $threads = Thread::with('channel')->latest()->filter($filters);

        if ($channel->exists) {
            $threads->where('channel_id', $channel->id);
        }

        $threads = $threads->paginate(20);
        return $threads;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('threads.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
           'title' => 'required|spamfree',
           'body'  => 'required|spamfree',
           'channel_id' => 'required|exists:channels,id'
        ]);

        $thread = Thread::create([
           'user_id' => auth()->id(),
           'channel_id' => request('channel_id'),
           'title' => request('title'),
           'body' => request('body'),
           'slug' => request('title'),
        ]);

        if ($request->wantsJson()) {
            return response($thread, 201);
        }

        return redirect($thread->path())->with('flash', 'Published Success');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function show($channelId, Thread $thread)
    {
        if (auth()->check()) {
            auth()->user()->read($thread);
        }

        (new Trending())->push($thread);

       // $thread->visits()->record();
        $thread->increment('visits');

        return view('threads.show', compact('thread'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function edit(Thread $thread)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function update($channelId, Thread $thread)
    {
        $this->authorize('update', $thread);

        if (request()->has('locked')) {
            if (! auth()->user()->isAdmin()) {
                return response('', 403);
            }

            $thread->lock();
        }

        $thread->update(\request()->validate([
            'title' => 'required|spamfree',
            'body'  => 'required|spamfree',
        ]));

        return $thread;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Thread  $thread
     * @return \Illuminate\Http\Response
     */
    public function destroy($channel, Thread $thread)
    {
        $this->authorize('update', $thread);

        $thread->delete();

        if (\request()->wantsJson()){
            return response([], 204);
        }

        return redirect('/threads');
    }


}
