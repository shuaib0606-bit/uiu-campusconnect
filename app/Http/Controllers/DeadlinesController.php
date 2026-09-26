<?php

namespace App\Http\Controllers;

use App\Support\Deadlines;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Everything due in the next few weeks, in one list. */
class DeadlinesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $items = Deadlines::for($user, 60);
        $today = Carbon::today();
        $endOfWeek = $today->copy()->addDays(6);

        $groups = $items->groupBy(function ($item) use ($today, $endOfWeek) {
            $day = $item['at']->copy()->startOfDay();

            return match (true) {
                $item['state'] === 'overdue' => 'Overdue',
                $day->lessThan($today) || $item['state'] === 'now' => 'Recently',
                $day->equalTo($today) => 'Today',
                $day->lessThanOrEqualTo($endOfWeek) => 'This week',
                $day->lessThanOrEqualTo($today->copy()->addDays(13)) => 'Next week',
                default => 'Later',
            };
        });

        $order = ['Overdue', 'Recently', 'Today', 'This week', 'Next week', 'Later'];

        return view('deadlines.index', [
            'groups' => collect($order)->filter(fn ($k) => $groups->has($k))->mapWithKeys(fn ($k) => [$k => $groups[$k]]),
            'teacher' => Deadlines::isTeacher($user),
        ]);
    }
}
