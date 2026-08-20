<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LostFoundController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SectionAnnouncementController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'welcome'])->name('welcome');

Route::post('/theme', ThemeController::class)->name('theme.update');

// Guest-only auth routes
Route::middleware('guest')->group(function () {
    Route::get('/signup', [RegisterController::class, 'create'])->name('register');
    Route::post('/signup', [RegisterController::class, 'store']);
    Route::get('/signin', [LoginController::class, 'create'])->name('login');
    Route::post('/signin', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.attempt');
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

// Authenticated app routes
Route::middleware('auth')->group(function () {
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');
    Route::put('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::post('/posts/{post}/save', [PostController::class, 'toggleSave'])->name('posts.save');
    Route::post('/posts/{post}/hide', [PostController::class, 'hide'])->name('posts.hide');
    Route::post('/posts/{post}/share', [PostController::class, 'share'])->name('posts.share');
    Route::post('/posts/{post}/report', [PostController::class, 'report'])->name('posts.report');
    Route::post('/posts/{post}/react', [ReactionController::class, 'store'])->name('posts.react');
    Route::post('/posts/{post}/vote', [PostController::class, 'vote'])->name('posts.vote');

    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/like', [CommentController::class, 'like'])->name('comments.like');
    Route::post('/comments/{comment}/report', [CommentController::class, 'report'])->name('comments.report');

    Route::get('/me', [ProfileController::class, 'me'])->name('profile.me');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/{user}/report', [ProfileController::class, 'report'])->name('profile.report');

    Route::get('/settings/password', [SettingsController::class, 'passwordForm'])->name('settings.password');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');

    Route::get('/network', [NetworkController::class, 'index'])->name('network.index');
    Route::post('/network/{user}/request', [NetworkController::class, 'request'])->name('network.request');
    Route::post('/network/{user}/accept', [NetworkController::class, 'accept'])->name('network.accept');
    Route::post('/network/{user}/decline', [NetworkController::class, 'decline'])->name('network.decline');
    Route::post('/network/{user}/remove', [NetworkController::class, 'remove'])->name('network.remove');
    Route::post('/network/{user}/follow', [NetworkController::class, 'follow'])->name('network.follow');
    Route::post('/network/{user}/block', [NetworkController::class, 'block'])->name('network.block');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{user}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages/{user}', [MessageController::class, 'store'])->name('messages.store');

    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/{club:slug}', [ClubController::class, 'show'])->name('clubs.show');
    Route::post('/clubs/{club}/join', [ClubController::class, 'join'])->name('clubs.join');
    Route::post('/clubs/{club}/leave', [ClubController::class, 'leave'])->name('clubs.leave');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::post('/events/{event}/register', [EventController::class, 'register'])->name('events.register');
    Route::post('/events/{event}/interest', [EventController::class, 'interest'])->name('events.interest');
    Route::post('/events/{event}/checkin', [EventController::class, 'checkin'])->name('events.checkin');

    Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
    Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');

    Route::get('/administration', [SectionAnnouncementController::class, 'index'])->name('administration.index');
    Route::post('/administration', [SectionAnnouncementController::class, 'store'])->name('administration.store');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/{group:slug}', [GroupController::class, 'show'])->name('groups.show');
    Route::post('/groups/{group}/join', [GroupController::class, 'join'])->name('groups.join');

    Route::get('/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
    Route::post('/marketplace', [MarketplaceController::class, 'store'])->name('marketplace.store');

    Route::get('/lost-found', [LostFoundController::class, 'index'])->name('lost-found.index');
    Route::post('/lost-found', [LostFoundController::class, 'store'])->name('lost-found.store');
    Route::post('/lost-found/{item}/claim', [LostFoundController::class, 'claim'])->name('lost-found.claim');

    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});
