<?php

namespace App;

use App\Traits\RecordsActivity;
use App\Traits\RecordsVisits;
use Illuminate\Database\Eloquent\Model;
use App\Events\ThreadReceivedNewReply;

class Thread extends Model
{
    use RecordsActivity;

    protected $guarded = [];

    protected $with = ['creator', 'channel'];

    protected $appends = ['isSubscribedTo'];

    public function getRouteKeyName()
    {
        return 'slug'; // TODO: Change the autogenerated stub
    }
    public function setSlugAttribute($value)
    {
        if(static::whereSlug($slug = str_slug($value))->exists()) {
            $slug = $this->incrementSlug($slug);
        }

        $this->attributes['slug'] = $slug;
    }

    public function incrementSlug($slug)
    {
        // 取出最大 id 话题的 Slug 值
        $max = static::whereTitle($this->title)->latest('id')->value('slug');

        // 如果最后一个字符为数字
        if(is_numeric($max[-1])) {
            // 正则匹配出末尾的数字，然后自增 1
            return preg_replace_callback('/(\d+)$/',function ($matches) {
                return $matches[1]+1;
            },$max);
        }

        // 否则后缀数字为 2
        return "{$slug}-2";
    }

    public function path()
    {
        return '/threads/'.$this->channel->slug.'/'.$this->slug;
    }

    public function replies()
    {
        return $this->hasMany(Reply::class)
            ->withCount('favorites')
            ->with('owner');
    }

    public function favorites()
    {
        return $this->morphMany(Favorite::class, 'favorited');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function addReply($reply)
    {
      $reply = $this->replies()->create($reply);
        //使用事件模型
      event(new ThreadReceivedNewReply($reply));

      return $reply;
    }

    public function notifySubscribers($reply)
    {
        $this->subscriptions
            ->where('user_id','!=',$reply->user_id)
            ->each
            ->notify($reply);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function scopeFilter($query, $filters)
    {
        return $filters->apply($query);
    }

    protected static function boot()
    {
        parent::boot(); // TODO: Change the autogenerated stub
//        //添加全局作用域
//        static::addGlobalScope('replyCount', function ($builder){
//            $builder->withCount('replies');
//        });


        static::deleting(function($thread){
            $thread->replies->each->delete();
        });

    }

    public function subscribe($userId = null)
    {
        $this->subscriptions()->create([
           'user_id' => $userId ?: auth()->id()
        ]);

        return $this;
    }

    public function unsubscribe($userId = null)
    {
        $this->subscriptions()
            ->where('user_id',$userId ?: auth()->id())
            ->delete();
    }

    public function subscriptions()
    {
        return $this->hasMany(ThreadSubscription::class);
    }

    public function getIsSubscribedToAttribute()
    {
        return $this->subscriptions()
                    ->where('user_id', auth()->id())
                    ->exists();
    }

    public function hasUpdatesFor(User $user)
    {
        $key = $user->visitedThreadCacheKey($this);

        return $this->updated_at > cache($key);
    }

    public function visits()
    {
        return new Visits($this);
    }

}
