<?php

namespace App\Http\Controllers;

use App\Group;
use App\Marks;
use App\MarksRoles;
use Illuminate\Http\Request;
use App\AcademicDisciplines;
use App\User;
use Illuminate\Support\Facades\Auth;
use Session;
use Excel;
use File;

class MarkController extends Controller
{
    public function index(User $user, AcademicDisciplines $discipline)
    {
        $students = User::where('group_id', '=', $user->group_id)->get();
        $marks = Marks::orderBy('date', 'desc')->get();
        $exams = $user->examMarks()->get();
        $avg = $exams->avg('mark');
        $fill = (100 * $avg) / 5;
        $month = ["01" => "Січня", "02" => "Лютого", "03" => "Березня", "04" => "Квітня", "05" => "Травня", "06" => "Червня", "07" => "Липня",
            "08" => "Серпня", "09" => "Вересня", "10" => "Жовтня", "11" => "Листопада", "12" => "Грудня"];

        if (auth()->check()) {
            if (auth()->user()->id == $user->id || auth()->user()->hasRole('admin') || auth()->user()->hasRole('teacher') || auth()->user()->hasRole('operator')) {
                return view('marks', ['user' => $user, 'students' => $students, 'marks' => $marks, 'avg' => $avg, 'fill' => $fill, 'month' => $month]);
            } else {
                return redirect()->route('marks', auth()->user()->id);
            }
        } else {
            return redirect('/');
        }
    }

    public function showForm(Group $group, User $user, Marks $marks, MarksRoles $marksRoles)
    {
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('teacher') || auth()->user()->hasRole('operator')) {
            $marksRoles = MarksRoles::all();
            $students = User::all();
            return view('add_mark', ['students' => $students, 'group' => $group, 'marks' => $marks, 'marksRoles' => $marksRoles]);
        } else {
            return redirect()->back();
        }
    }

    public function editMark(Marks $mark, Group $group, User $user, MarksRoles $marksRoles)
    {
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('teacher') || auth()->user()->hasRole('operator')) {
            $marksRoles = MarksRoles::all();
            $students = User::all();
            return view('edit_mark', ['mark' => $mark, 'students' => $students, 'group' => $group, 'marksRoles' => $marksRoles]);
        } else {
            return redirect()->back();
        }
    }

    public function updateMark(Request $request, Marks $mark, Group $group)
    {
//        dd($request->input('submit'));
//        dd($request->input());
        $this->validate($request, [
            'mark' => 'required|integer|max:5',
            'date' => 'required|date|max:255',
            'comment' => 'max:255',
            'student_name' => 'required|string|max:255',
            'discipline_title' => 'required|integer|max:255',
            'role_title' => 'required|integer|max:255',
        ]);
        if ($request->input('mark') != "Оцінка..") {
            $mark->mark = $request->input('mark');
        } else {
            flash()->error('Виберіть оцінку!');
            return redirect()->back();
        }

        $mark->date = $request->input('date');
        $mark->comment = $request->input('comment');
        if ($request->input('student_name') != "Студент..") {
            $mark->user_id = $request->input('student_name');
        } else {
            flash()->error('Виберіть студента!');
            return redirect()->back();
        }

        if ($request->input('discipline_title') != "Предмет..") {
            $mark->discipline_id = $request->input('discipline_title');
        } else {
            flash()->error('Виберіть предмет!');
            return redirect()->back();
        }

        if ($request->input('role_title') != "Вид оцінки..") {
            $mark->role_id = $request->input('role_title');
        } else {
            flash()->error('Виберіть вид оцінки!');
            return redirect()->back();
        }
        $mark->save();
        flash()->success('Успішно оновлено');
        return redirect()->route('showGroupProfile', $group->id);
    }

    public function deleteMark(Marks $mark, Group $group)
    {
        if ($mark->delete())
        {
            flash()->success('Оцінка успішно видалена');
        }
        else
        {
            flash()->error('Оцінку не було видалено!');
        }
        return redirect()->route('showGroupProfile', $group->id);
    }

    public function addMark(Request $request, User $user)
    {
        $this->validate($request, [
            'mark' => 'required|integer|max:5',
            'date' => 'required|date|max:255',
            'comment' => 'max:255',
            'student_name' => 'required|string|max:255',
            'discipline_title' => 'required|integer|max:255',
            'role_title' => 'required|integer|max:255',
        ]);
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('teacher') || auth()->user()->hasRole('operator')) {
            if ($request->file() != NULL) {
                $extension = File::extension($request->file->getClientOriginalName());
                if ($extension == "xlsx" || $extension == "xls" || $extension == "csv") {

                    $path = $request->file->getRealPath();
                    $data = Excel::load($path, function ($reader) {
                    })->first();
                    if (!empty($data) && $data->count()) {

                        foreach ($data as $key => $value) {
                            if ($request->input('discipline_title') != "Предмет.." && $request->input('role_title') != "Вид оцінки..") {
                                $user = User::where('login', '=', $value->login)->first();
                                $insert[] = [
                                    'discipline_id' => $request->input('discipline_title'),
                                    'role_id' => $request->input('role_title'),
                                    'user_id' => $user->id,
                                    'mark' => $value->otsinka,
                                    'date' => $value->data,
                                    'comment' => $value->komentar,
                                ];
                            }
                        }
                        if (!empty($insert)) {
                            $marks = Marks::insert($insert);

                            if ($marks) {
                                flash()->success('Дані введені успішно');
                            } else {
                                flash()->error('Помилка про вводі даних!');
                                return back();
                            }
                        }
                    }

                    return back();

                } else {
                    Session::flash('error', 'File is a ' . $extension . ' file.!! Please upload a valid xls/csv file..!!');
                    return back();
                }
            } else {
                if ($request->input('mark') != "Оцінка..") {
                    $data['mark'] = $request->input('mark');
                } else {
                    flash()->error('Виберіть оцінку!');
                    return redirect()->back();
                }

                $data['date'] = $request->input('date');
                $data['comment'] = $request->input('comment');
                if ($request->input('student_name') != "Студент..") {
                    $data['user_id'] = $request->input('student_name');
                } else {
                    flash()->error('Виберіть студента!');
                    return redirect()->back();
                }

                if ($request->input('discipline_title') != "Предмет..") {
                    $data['discipline_id'] = $request->input('discipline_title');
                } else {
                    flash()->error('Виберіть предмет!');
                    return redirect()->back();
                }

                if ($request->input('role_title') != "Вид оцінки..") {
                    $data['role_id'] = $request->input('role_title');
                } else {
                    flash()->error('Виберіть вид оцінки!');
                    return redirect()->back();
                }

                $mark = Marks::create($data);
                if ($mark) {
                    flash()->success('Оцінка успішно додана');
                } else {
                    flash()->error('Оцінка не додана!');
                }
                return redirect()->back();
            }
        } else {
            return redirect()->back();
        }
    }
}
