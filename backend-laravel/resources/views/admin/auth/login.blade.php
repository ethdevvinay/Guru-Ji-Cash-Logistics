<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Portal Login — Guruji Cash Logistics</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            600: '#4F46E5',
                            700: '#4338CA',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full font-sans antialiased text-slate-800 flex items-center justify-center p-6 bg-slate-50">

    <div class="w-full max-w-md">
        <!-- Logo & Branding -->
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-indigo-600 items-center justify-center text-white shadow-xl shadow-indigo-500/20 font-extrabold text-2xl mb-4">
                G
            </div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Guruji Cash Logistics</h1>
            <p class="text-sm text-slate-500 mt-1">360° Cash Collection & Retailer Operations Platform</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl border border-slate-200 p-8 shadow-sm">
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900">Admin Control Sign-In</h2>
                <p class="text-xs text-slate-500 mt-0.5">Enter your official administrator credentials</p>
            </div>

            @if($errors->any())
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Official Mobile / Email</label>
                    <input type="text" name="email" id="email" value="{{ old('email', 'admin@rockvanta.com') }}" required 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-slate-900 placeholder-slate-400 font-medium">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Password</label>
                    </div>
                    <input type="password" name="password" id="password" value="Admin@2026#CMS" required 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-slate-900 font-medium">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-md shadow-indigo-600/20 transition-all flex items-center justify-center">
                        Access Control Center
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>
            </form>

            <!-- Test Credentials Quick-Fill Pill from Spec PDF Page 1 -->
            <div class="mt-6 pt-5 border-t border-slate-100">
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">Test Admin Account (Spec Page 1)</p>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs font-mono text-slate-600 flex items-center justify-between">
                    <div>
                        <p class="font-bold text-slate-800">admin@rockvanta.com</p>
                        <p class="text-[11px] text-slate-500">Pass: Admin@2026#CMS</p>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 text-[10px] font-bold">SUPER ADMIN</span>
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-slate-400 mt-6">
            &copy; 2026 Guruji Cash Logistics Platform. All Rights Reserved.
        </p>
    </div>

</body>
</html>
