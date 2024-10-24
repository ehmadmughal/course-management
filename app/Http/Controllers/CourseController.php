<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        // Load the JSON file
        $json = Storage::get('courses.json');
        $courses = json_decode($json, true);

        // Filter by month, venue, and type
        $month = $request->query('month');
        $venue = $request->query('venue');
        $type = $request->query('type');

        $filtered = array_filter($courses, function ($course) use ($month, $venue, $type) {
            $courseDate = Carbon::createFromFormat('D jS F Y', $course['formatted_start_date']);
//            dd($courseDate->format('F'));
            $matchesMonth = !$month || $courseDate->format('F') === $month;
            $matchesVenue = !$venue || $course['venue']['name'] === $venue;
            $matchesType = !$type || $this->matchesType($course, $type);

            return $matchesMonth && $matchesVenue && $matchesType;
        });

        // Aggregate courses
        $aggregated = $this->aggregateCourses($filtered);

        return response()->json($aggregated);
    }

    private function matchesType($course, $type)
    {
        if ($type === 'Monday to Friday') {
            return count($course['days']) === 5 && $this->isWeekdaySession($course['days']);
        } elseif ($type === 'Day Release') {
            return count($course['days']) > 1 && $this->isDayRelease($course['days']);
        } elseif ($type === 'Weekend') {
            return $this->isWeekendSession($course['days']);
        }

        return false;
    }

    private function isWeekdaySession($days)
    {
        // Check if the days span Monday to Friday
        $weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
        foreach ($days as $day) {
            $dayOfWeek = date('D', strtotime($day['start_date']));
            if (!in_array($dayOfWeek, $weekdays)) {
                return false;
            }
        }
        return true;
    }

    private function isDayRelease($days)
    {
        // Sessions occur once a week
        $previousWeek = null;
        foreach ($days as $day) {
            $week = date('W', strtotime($day['start_date']));
            if ($previousWeek && $previousWeek === $week) {
                return false;
            }
            $previousWeek = $week;
        }
        return true;
    }

    private function isWeekendSession($days)
    {
        // All days must be Saturday or Sunday
        foreach ($days as $day) {
            $dayOfWeek = date('D', strtotime($day['start_date']));
            if ($dayOfWeek !== 'Sat' && $dayOfWeek !== 'Sun') {
                return false;
            }
        }
        return true;
    }

    private function aggregateCourses($courses)
    {
        $aggregated = [];

        foreach ($courses as $course) {
            $key = $course['venue']['name'] . $course['formatted_start_date'] . $course['formatted_end_date'];

            if (!isset($aggregated[$key])) {
                $aggregated[$key] = $course;
            } else {
                $aggregated[$key]['available_spaces'] += $course['available_spaces'];
            }
        }

        return array_values($aggregated);
    }

    public function getSimilarCourses()
    {
        // Load the JSON file
        $json = Storage::get('courses.json');
        $courses = json_decode($json, true);

        // Group courses by formatted_start_date, formatted_end_date, and venue->name
        $groupedCourses = [];

        foreach ($courses as $course) {
            $key = $course['formatted_start_date'] . $course['formatted_end_date'] . $course['venue']['name'];

            if (!isset($groupedCourses[$key])) {
                $groupedCourses[$key] = [];
            }

            $groupedCourses[$key][] = $course;
        }

        // Filter out groups that have more than one course
        $similarCourses = array_filter($groupedCourses, function ($group) {
            return count($group) > 1;
        });

        return response()->json(array_values($similarCourses));
    }

}

