<?php
// routes/web.php
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

Route::get('/', function () {
    return view('welcome');
})->name('login');

Route::post('/login-web', function (Request $request) {
    // Gunakan logika scopeCheckLogin yang ada di model User kamu
    $user = User::checkLogin($request->username, $request->password)->first();

    if ($user) {
        // Login secara session (Web)
        Auth::login($user);
        $request->session()->regenerate();
        
        return redirect('/api/documentation');
    }

    return back()->withErrors(['msg' => 'Username atau Password salah']);
})->name('login.web');

Route::post('/logout-web', function (Request $request) {
    Auth::logout();
    return redirect('/');
})->name('logout.web');