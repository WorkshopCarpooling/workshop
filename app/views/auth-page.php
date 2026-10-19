<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8f5">
    <title><?= $isSignup ? 'Sign up' : 'Log in' ?> Â· RideUTeM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page <?= $isSignup ? 'signup-page' : 'login-page' ?>">
    <main class="auth-shell">
        <section class="visual-panel" aria-label="RideUTeM campus carpooling">
            <a class="brand brand-light" href="login.php" aria-label="RideUTeM home">
                <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 32 32"><path d="M5 18.5 8.2 10a3 3 0 0 1 2.8-1.9h10a3 3 0 0 1 2.8 1.9l3.2 8.5v7a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5v-2H9v2a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 5 25.5v-7Zm3.1-1.2h15.8l-2.4-6.1a.8.8 0 0 0-.7-.5H11.2a.8.8 0 0 0-.7.5l-2.4 6.1ZM9 21.1a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Zm14 0a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Z"/><path d="M4 5.5h8M4 5.5l2.3-2.3M4 5.5l2.3 2.3"/></svg></span>
                <span>ride<span>UTeM</span></span>
            </a>

            <div class="panel-copy">
                <div class="eyebrow"><span class="live-dot"></span> A better way to campus</div>
                <h1>Good journeys<br>are <em>better shared.</em></h1>
                <p>Find your people, share the ride, and make every trip to UTeM count.</p>
            </div>

            <div class="journey-art" aria-hidden="true">
                <div class="sun-glow"></div>
                <div class="hill hill-back"></div><div class="hill hill-front"></div>
                <div class="road"></div>
                <svg class="car-art" viewBox="0 0 560 250" role="img">
                    <defs>
                        <linearGradient id="carpaint" x1="0" x2="1" y1="0" y2="1"><stop stop-color="#fff"/><stop offset="1" stop-color="#dfe8df"/></linearGradient>
                        <linearGradient id="glass" x1="0" x2="1"><stop stop-color="#98b6a9"/><stop offset="1" stop-color="#31534b"/></linearGradient>
                        <filter id="shadow" x="-30%" y="-50%" width="160%" height="220%"><feGaussianBlur stdDeviation="7"/></filter>
                    </defs>
                    <ellipse cx="290" cy="210" rx="218" ry="19" fill="#122a26" opacity=".26" filter="url(#shadow)"/>
                    <path d="m70 170 20-34c10-17 26-29 46-34l84-18 53-49c12-11 27-17 43-17h56c24 0 46 12 60 31l42 58 41 16c16 6 27 21 27 38v31H64v-9c0-13 1-22 6-33Z" fill="url(#carpaint)" stroke="#c9d6cb" stroke-width="3"/>
                    <path d="m238 92 49-45c8-8 18-12 29-12h54c15 0 29 8 38 20l31 43-201-6Z" fill="url(#glass)"/>
                    <path d="m218 98 13 1 13-56-16 4-46 49 36 2Zm164-62h-36v59l100 3-29-42c-8-12-21-20-35-20Z" fill="#dce8df" opacity=".72"/>
                    <path d="M66 163c27-13 54-18 78-18h301c40 0 70 13 98 30l7 12H64Z" fill="#f7faf6" opacity=".56"/>
                    <path d="M69 167h42m401 5h30" stroke="#f2dca6" stroke-width="9" stroke-linecap="round"/><path d="M71 179h23" stroke="#f9f6e9" stroke-width="5" stroke-linecap="round"/>
                    <path d="M128 190a39 39 0 1 0 78 0 39 39 0 0 0-78 0Zm278 0a39 39 0 1 0 78 0 39 39 0 0 0-78 0Z" fill="#1d302d"/><path d="M139 190a28 28 0 1 0 56 0 28 28 0 0 0-56 0Zm278 0a28 28 0 1 0 56 0 28 28 0 0 0-56 0Z" fill="#d4ded8"/><path d="M151 190a16 16 0 1 0 32 0 16 16 0 0 0-32 0Zm278 0a16 16 0 1 0 32 0 16 16 0 0 0-32 0Z" fill="#879c92"/>
                    <path d="M515 150c12 6 19 13 24 22h-26l-7-19 9-3ZM91 147c-11 7-18 15-23 26h23l9-20-9-6Z" fill="#f5b45f"/>
                </svg>
                <div class="route-chip"><span class="route-icon">â†—</span><span><b>UTeM Main Campus</b><small>Durian Tunggal Â· 18 min</small></span><span class="route-arrow">â†’</span></div>
                <div class="ride-note"><span class="avatar-stack"><i>F</i><i>A</i><i>+</i></span><span><b>Seats feel better together</b><small>Meet your campus community</small></span></div>
            </div>
            <div class="panel-footer"><span>Designed for the UTeM community</span><span class="footer-spark">âœ³</span><span>Move together, thoughtfully.</span></div>
        </section>

        <section class="form-panel">
            <div class="form-top"><a class="brand brand-dark" href="login.php"><span class="brand-mark"><svg viewBox="0 0 32 32"><path d="M5 18.5 8.2 10a3 3 0 0 1 2.8-1.9h10a3 3 0 0 1 2.8 1.9l3.2 8.5v7a1.5 1.5 0 0 1-1.5 1.5h-1a1.5 1.5 0 0 1-1.5-1.5v-2H9v2a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 5 25.5v-7Zm3.1-1.2h15.8l-2.4-6.1a.8.8 0 0 0-.7-.5H11.2a.8.8 0 0 0-.7.5l-2.4 6.1ZM9 21.1a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Zm14 0a1.7 1.7 0 1 0 0-3.4 1.7 1.7 0 0 0 0 3.4Z"/></svg></span><span>ride<span>UTeM</span></span></a><span class="secure-note"><svg viewBox="0 0 20 20"><path d="M10 2 4 4.5v4.3c0 4 2.6 7.4 6 8.7 3.4-1.3 6-4.7 6-8.7V4.5L10 2Zm-1 10.5L6.5 10l1.1-1.1L9 10.3l3.4-3.4L13.5 8 9 12.5Z"/></svg>UTeM community</span></div>

            <div class="form-wrap <?= $isSignup ? 'form-wrap-wide' : '' ?>">
                <div class="form-heading">
                    <div class="welcome-tag"><span>âœ¦</span> YOUR CAMPUS, YOUR COMMUNITY</div>
                    <h2><?= $isSignup ? 'Find your way<br>to <em>better rides.</em>' : 'Welcome<br><em>back.</em>' ?></h2>
                    <p><?= $isSignup ? 'Create an account as a passenger or driver in the UTeM carpool community.' : 'Your next campus ride is just around the corner.' ?></p>
                </div>

                <form class="auth-form" id="authForm" method="post" novalidate data-mode="<?= $isSignup ? 'signup' : 'login' ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($isSignup): ?>
                    <label class="field-label" for="fullName">Full name</label>
                    <div class="input-wrap"><span class="input-icon"><svg viewBox="0 0 20 20"><circle cx="10" cy="6.2" r="3"/><path d="M4.2 17c.2-3.3 2.5-5.1 5.8-5.1s5.6 1.8 5.8 5.1"/></svg></span><input id="fullName" name="name" type="text" placeholder="Your name as it appears on your ID" autocomplete="name" required></div>
                    <label class="field-label" for="email">UTeM email address</label>
                    <?php else: ?>
                    <label class="field-label" for="email">University email</label>
                    <?php endif; ?>
                    <div class="input-wrap"><span class="input-icon"><svg viewBox="0 0 20 20"><rect x="2.7" y="4" width="14.6" height="12" rx="2"/><path d="m3.5 5.2 6.5 5 6.5-5"/></svg></span><input id="email" name="email" type="email" placeholder="name@university.edu" autocomplete="email" required></div>
                    <?php if ($isSignup): ?><p class="field-hint"><span class="verified-mark">âœ“</span> Use your official UTeM email so an administrator can review your account.</p>
                    <label class="field-label" for="memberType">Iâ€™m joining as</label><div class="select-wrap"><span class="input-icon"><svg viewBox="0 0 20 20"><circle cx="7" cy="6" r="2.5"/><path d="M2.5 16c.2-2.8 1.8-4.4 4.5-4.4s4.3 1.6 4.5 4.4M13 5.5a2.4 2.4 0 0 1 0 4.6m1 2c2 .3 3.2 1.6 3.4 3.9"/></svg></span><select id="memberType" name="memberType" required><option value="" disabled selected>Select your community</option><option value="student">Student</option><option value="staff">UTeM staff</option></select><span class="select-chevron">âŒ„</span></div><label class="field-label" for="accountType">How will you use RideUTeM?</label><div class="select-wrap"><span class="input-icon">â†—</span><select id="accountType" name="accountType" required><option value="" disabled selected>Choose your account type</option><option value="passenger">Passenger â€” find rides</option><option value="driver">Driver â€” offer rides</option></select><span class="select-chevron">âŒ„</span></div><p class="field-hint">Students can choose passenger or driver and offer rides for part-time work. New accounts need admin approval.</p><?php endif; ?>
                    <div class="password-label"><label class="field-label" for="password">Password</label><?php if (!$isSignup): ?><a href="#" class="quiet-link" id="forgotLink">Forgot password?</a><?php endif; ?></div>
                    <div class="input-wrap"><span class="input-icon"><svg viewBox="0 0 20 20"><rect x="3" y="8.5" width="14" height="9" rx="2"/><path d="M6 8.5V6a4 4 0 1 1 8 0v2.5"/></svg></span><input id="password" name="password" type="password" placeholder="<?= $isSignup ? 'At least 8 characters' : 'Enter your password' ?>" autocomplete="<?= $isSignup ? 'new-password' : 'current-password' ?>" minlength="8" required><button class="show-password" type="button" aria-label="Show password" data-password-toggle="password"><svg viewBox="0 0 20 20"><path d="M2 10s2.8-5 8-5 8 5 8 5-2.8 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/></svg></button></div>
                    <?php if ($isSignup): ?><label class="field-label" for="confirmPassword">Confirm password</label><div class="input-wrap"><span class="input-icon"><svg viewBox="0 0 20 20"><rect x="3" y="8.5" width="14" height="9" rx="2"/><path d="M6 8.5V6a4 4 0 1 1 8 0v2.5"/></svg></span><input id="confirmPassword" name="confirmPassword" type="password" placeholder="Enter your password again" autocomplete="new-password" minlength="8" required><button class="show-password" type="button" aria-label="Show password" data-password-toggle="confirmPassword"><svg viewBox="0 0 20 20"><path d="M2 10s2.8-5 8-5 8 5 8 5-2.8 5-8 5-8-5-8-5Z"/><circle cx="10" cy="10" r="2.2"/></svg></button></div>
                    <label class="preference-row"><input type="checkbox" name="genderPreference"><span class="fake-check"></span><span>Show me rides with a same-gender preference <small>This is a matching preference, not a safety guarantee.</small></span></label><?php endif; ?>
                    <?php if (!$isSignup): ?><label class="remember-row"><input type="checkbox" name="remember"><span class="fake-check"></span><span>Remember me on this device</span></label><?php endif; ?>
                    <div class="form-message <?= $formError !== '' ? 'error' : ($formSuccess !== '' ? 'success' : '') ?>" id="formMessage" role="status" aria-live="polite"><?= htmlspecialchars($formError !== '' ? $formError : $formSuccess, ENT_QUOTES, 'UTF-8') ?></div>
                    <button class="submit-button" type="submit"><?= $isSignup ? 'Create account' : 'Log in' ?><span>â†’</span></button>
                    <div class="form-divider"><span>or continue with</span></div>
                    <button class="google-button" type="button" id="googleButton"><span class="google-g">G</span> Continue with Google</button>
                </form>

                <p class="switch-prompt"><?= $isSignup ? 'Already have an account?' : 'New to the community?' ?> <a href="<?= $isSignup ? 'login.php' : 'signup.php' ?>"><?= $isSignup ? 'Log in' : 'Create an account' ?> <span>â†—</span></a></p>
                <p class="privacy-note"><svg viewBox="0 0 20 20"><path d="M10 2 4 4.5v4.3c0 4 2.6 7.4 6 8.7 3.4-1.3 6-4.7 6-8.7V4.5L10 2Z"/></svg>Your details stay within the verified UTeM community.</p>
            </div>
            <footer class="form-footer"><span>Â© 2026 RideUTeM</span><a href="#">Help centre</a><a href="#">Privacy</a><a href="dashboard.php">Dashboard preview</a></footer>
        </section>
    </main>
    <script src="js/auth.js" defer></script>
</body>
</html>
