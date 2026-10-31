<?php
// Public landing page for AKK SOLUTIONS Leave Management.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A professional leave management platform for teams and organizations.">
    <title>AKK SOLUTIONS | Automated Management System</title>
    <meta name="google-site-verification" content="XbDTUMzlMYWVsVgRjG9h0c6KeLgpPvC6pF32hgBLgPE" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #020617;
            --bg-2: #0f172a;
            --card: rgba(15,23,42,0.82);
            --card-strong: #0f172a;
            --line: rgba(71, 85, 105, 0.7);
            --text: #f8fafc;
            --muted: #94a3b8;
            --primary: #6366f1;
            --primary-2: #a855f7;
            --primary-soft: rgba(99,102,241,0.15);
            --accent: #f59e0b;
            --accent-soft: #fff5d8;
            --green: #10b981;
            --green-soft: #dcfce7;
            --shadow: 0 24px 60px rgba(16, 35, 54, 0.12);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(20,184,166,0.12), transparent 30%),
                radial-gradient(circle at bottom right, rgba(59,130,246,0.08), transparent 34%),
                var(--bg);
            color: var(--text);
        }

        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; }

        .container { width: min(1440px, calc(100% - 48px)); margin: 0 auto; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(16px);
            background: rgba(244, 248, 251, 0.75);
            border-bottom: 1px solid rgba(123, 146, 167, 0.12);
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 82px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            letter-spacing: -0.04em;
            font-size: 1.1rem;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, #0f172a, #0f766e);
            color: white;
            box-shadow: 0 12px 24px rgba(15, 118, 110, 0.25);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            color: var(--muted);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .nav-links a:hover { color: var(--text); }

        .login-btn, .primary-btn, .secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 14px;
            font-weight: 700;
            transition: 0.2s ease;
        }

        .login-btn {
            padding: 12px 18px;
            background: rgba(15, 118, 110, 0.08);
            border: 1px solid rgba(15, 118, 110, 0.15);
            color: var(--primary);
        }

        .login-btn:hover { transform: translateY(-2px); background: rgba(15,118,110,0.12); }

        .hero {
            max-width: 860px;
            padding: clamp(48px, 7vw, 88px) 0 64px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-radius: 999px;
            background: var(--primary-soft);
            border: 1px solid rgba(20,184,166,0.18);
            color: var(--primary);
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .eyebrow .dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--primary-2);
            box-shadow: 0 0 0 6px rgba(20,184,166,0.12);
        }

        h1 {
            margin: 22px 0 18px;
            font-size: clamp(2.8rem, 5vw, 5rem);
            line-height: 0.98;
            letter-spacing: -0.065em;
            max-width: 780px;
        }

        h1 .highlight {
            color: var(--primary);
        }

        .lead {
            max-width: 680px;
            margin: 0 0 30px;
            color: var(--muted);
            font-size: 1.08rem;
            line-height: 1.8;
        }

        .cta-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .primary-btn {
            padding: 16px 22px;
            background: linear-gradient(135deg, var(--primary), var(--primary-2));
            color: white;
            box-shadow: 0 18px 30px rgba(20,184,166,0.24);
        }

        .primary-btn:hover { transform: translateY(-2px); }

        .secondary-btn {
            padding: 16px 20px;
            background: rgba(255,255,255,0.7);
            border: 1px solid var(--line);
            color: var(--text);
        }

        .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
            margin-top: 28px;
            color: var(--muted);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .meta-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .meta-item i {
            color: var(--green);
            background: var(--green-soft);
            width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 0.75rem;
        }

        .dashboard-panel {
            position: relative;
            padding: 16px;
            border-radius: 30px;
            background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(236,245,249,0.96));
            border: 1px solid rgba(142,165,183,0.18);
            box-shadow: var(--shadow);
            min-height: 520px;
        }

        .dashboard-panel::before {
            content: "";
            position: absolute;
            inset: 24px auto auto 28px;
            width: 88%;
            height: 82%;
            border-radius: 26px;
            background: linear-gradient(135deg, rgba(20,184,166,0.08), rgba(59,130,246,0.04));
            border: 1px solid rgba(20,184,166,0.12);
            transform: rotate(-6deg);
            z-index: 0;
        }

        .dashboard-content {
            position: relative;
            z-index: 1;
            background: rgba(255,255,255,0.82);
            border: 1px solid rgba(133, 153, 170, 0.16);
            border-radius: 24px;
            padding: 22px;
            backdrop-filter: blur(12px);
        }

        .panel-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .panel-title {
            font-weight: 800;
            letter-spacing: -0.04em;
        }

        .panel-chip {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 800;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin: 20px 0 18px;
        }

        .stat-box {
            background: rgba(244, 250, 249, 0.9);
            border: 1px solid rgba(20,184,166,0.08);
            border-radius: 16px;
            padding: 16px 12px;
        }

        .stat-box strong {
            display: block;
            font-size: 1.6rem;
            letter-spacing: -0.06em;
        }

        .stat-box span {
            display: block;
            margin-top: 4px;
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .request-list {
            display: grid;
            gap: 14px;
            margin-top: 4px;
        }

        .request-item {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(248, 250, 252, 0.85);
            border: 1px solid rgba(135, 149, 163, 0.14);
            border-radius: 16px;
            padding: 14px 12px;
        }

        .request-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary);
        }

        .request-copy {
            flex: 1;
            min-width: 0;
        }

        .request-copy h4 {
            margin: 0;
            font-size: 0.92rem;
            letter-spacing: -0.03em;
        }

        .request-copy p {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 0.74rem;
        }

        .status-badge {
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .approved { background: var(--green-soft); color: #047857; }
        .pending { background: var(--accent-soft); color: #9a6700; }

        .floating-card {
            position: absolute;
            right: -6px;
            bottom: 20px;
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(123,146,167,0.18);
            border-radius: 16px;
            box-shadow: 0 16px 35px rgba(16,35,54,0.12);
            padding: 12px 16px;
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text);
        }

        .floating-card i { color: var(--accent); margin-right: 8px; }

        .features {
            padding: 24px 0 80px;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 26px;
        }

        .section-head h2 {
            margin: 0;
            font-size: clamp(2rem, 4vw, 3rem);
            letter-spacing: -0.06em;
        }

        .section-head p {
            margin: 0;
            color: var(--muted);
            max-width: 520px;
            line-height: 1.7;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: rgba(255,255,255,0.7);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 14px 28px rgba(15,23,42,0.04);
        }

        .feature-icon-box {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 1.2rem;
            margin-bottom: 18px;
        }

        .feature-card h3 {
            margin: 0 0 10px;
            font-size: 1.2rem;
            letter-spacing: -0.04em;
        }

        .feature-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.75;
            font-size: 0.96rem;
        }

        .steps {
            padding: 0 0 80px;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
        }

        .step-card {
            background: rgba(255,255,255,0.7);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 26px 22px;
        }

        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(15,118,110,0.08);
            color: var(--primary);
            font-weight: 800;
            margin-bottom: 14px;
        }

        .step-card h4 {
            margin: 0 0 8px;
            font-size: 1.05rem;
            letter-spacing: -0.03em;
        }

        .step-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.92rem;
        }

        .banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 30px 32px;
            border-radius: 30px;
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
            color: white;
            box-shadow: 0 22px 42px rgba(15, 23, 42, 0.15);
        }

        .banner h3 {
            margin: 0 0 8px;
            font-size: clamp(1.6rem, 3vw, 2.4rem);
            letter-spacing: -0.06em;
        }

        .banner p {
            margin: 0;
            opacity: 0.82;
            line-height: 1.7;
            max-width: 600px;
        }

        footer {
            border-top: 1px solid var(--line);
            padding: 22px 0 42px;
            color: var(--muted);
            font-size: 0.88rem;
        }

        .footer-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
        }

        @media (max-width: 980px) {
            .hero { grid-template-columns: 1fr; gap: 32px; padding: 48px 0 56px; }
            .feature-grid { grid-template-columns: 1fr; }
            .steps-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .banner { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 640px) {
            .container { width: calc(100% - 32px); }
            .nav-links { gap: 0; }
            .nav-links a:not(.login-btn) { display: none; }
            .hero { gap: 28px; padding: 40px 0 48px; }
            .features, .steps { padding-bottom: 56px; }
            .feature-card, .step-card { padding: 24px 20px; }
            .dashboard-content { padding: 18px; }
            .stats { grid-template-columns: 1fr; }
            .steps-grid { grid-template-columns: 1fr; }
            .section-head { display: block; }
            .floating-card { position: static; margin-top: 14px; }
            .dashboard-panel { min-height: auto; }
            .footer-row { display: block; }
            .nav { height: 72px; }
        }

        /* Match the authenticated AKK SOLUTIONS experience. */
        body {
            background:
                radial-gradient(circle at 8% 8%, rgba(99,102,241,0.18), transparent 28%),
                radial-gradient(circle at 92% 84%, rgba(168,85,247,0.14), transparent 30%),
                #020617;
            color: #f8fafc;
        }
        .topbar {
            background: rgba(2, 6, 23, 0.78);
            border-bottom-color: rgba(51, 65, 85, 0.7);
        }
        .brand { letter-spacing: 0.02em; }
        .brand-mark {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            box-shadow: 0 12px 28px rgba(99,102,241,0.32);
        }
        .nav-links { color: #94a3b8; }
        .nav-links a:hover { color: #fff; }
        .login-btn {
            color: #fcd34d;
            background: rgba(30,41,59,0.64);
            border-color: rgba(245,158,11,0.22);
        }
        .login-btn:hover { background: rgba(245,158,11,0.1); }
        .eyebrow {
            color: #a5b4fc;
            background: rgba(99,102,241,0.12);
            border-color: rgba(99,102,241,0.25);
        }
        .eyebrow .dot { background: #818cf8; box-shadow: 0 0 0 6px rgba(99,102,241,0.13); }
        h1 .highlight { color: #a78bfa; }
        .lead, .section-head p, .feature-card p, .step-card p { color: #94a3b8; }
        .primary-btn {
            background: linear-gradient(90deg, #059669, #0d9488);
            box-shadow: 0 18px 30px rgba(16,185,129,0.22);
        }
        .secondary-btn {
            color: #e2e8f0;
            background: rgba(30,41,59,0.62);
            border-color: rgba(71,85,105,0.75);
        }
        .meta-row { color: #94a3b8; }
        .meta-item i { color: #34d399; background: rgba(16,185,129,0.13); }
        .dashboard-panel {
            background: linear-gradient(145deg, rgba(30,41,59,0.95), rgba(15,23,42,0.98));
            border-color: rgba(99,102,241,0.22);
            box-shadow: 0 24px 70px rgba(0,0,0,0.34);
        }
        .dashboard-panel::before {
            background: linear-gradient(135deg, rgba(99,102,241,0.17), rgba(168,85,247,0.08));
            border-color: rgba(99,102,241,0.17);
        }
        .dashboard-content {
            background: rgba(15,23,42,0.86);
            border-color: rgba(71,85,105,0.65);
        }
        .panel-top, .request-item { border-color: rgba(51,65,85,0.75); }
        .panel-chip { background: rgba(99,102,241,0.18); color: #a5b4fc; }
        .stat-box, .request-item {
            background: rgba(30,41,59,0.62);
            border-color: rgba(71,85,105,0.46);
        }
        .stat-box span, .request-copy p { color: #94a3b8; }
        .request-icon { background: rgba(99,102,241,0.15); color: #a5b4fc; }
        .floating-card {
            color: #e2e8f0;
            background: rgba(15,23,42,0.95);
            border-color: rgba(71,85,105,0.72);
        }
        .feature-card, .step-card {
            background: rgba(15,23,42,0.68);
            border-color: rgba(51,65,85,0.75);
        }
        .feature-icon-box {
            color: #a5b4fc;
            background: rgba(99,102,241,0.15);
        }
        .step-number { color: #a5b4fc; background: rgba(99,102,241,0.15); }
        .banner {
            background: linear-gradient(135deg, rgba(49,46,129,0.95), rgba(88,28,135,0.95));
            border: 1px solid rgba(139,92,246,0.35);
        }
        footer { border-top-color: rgba(51,65,85,0.7); color: #64748b; }
        /* Refined landing-page spacing, surfaces, and responsive alignment. */
        :root {
            --line: rgba(100, 116, 139, 0.34);
            --muted: #a5b4c8;
            --shadow: 0 28px 70px rgba(0, 0, 0, 0.34);
        }

        body {
            background:
                radial-gradient(ellipse at 18% 18%, rgba(99,102,241,0.17), transparent 34%),
                radial-gradient(ellipse at 88% 58%, rgba(124,58,237,0.12), transparent 32%),
                #020617;
        }

        .topbar {
            background: rgba(2, 6, 23, 0.88);
            border-bottom-color: rgba(71, 85, 105, 0.42);
        }

        .nav { height: 76px; }
        .brand { gap: 12px; font-size: 1.02rem; }
        .brand-mark { border-radius: 13px; }
        .nav-links { gap: clamp(18px, 2.2vw, 32px); }
        .login-btn { border-radius: 13px; padding: 11px 17px; }

        .hero {
            max-width: 860px;
            padding: clamp(64px, 7vw, 96px) 0 76px;
        }

        h1 {
            font-size: clamp(3rem, 5.1vw, 5rem);
            line-height: 1.02;
            letter-spacing: -0.06em;
        }

        .lead { line-height: 1.75; }
        .eyebrow { letter-spacing: 0.1em; }
        .secondary-btn {
            color: #e2e8f0;
            background: rgba(30, 41, 59, 0.68);
            border-color: rgba(100, 116, 139, 0.42);
            border-radius: 13px;
        }
        .secondary-btn:hover, .login-btn:hover {
            transform: translateY(-2px);
            border-color: rgba(129, 140, 248, 0.55);
        }

        .dashboard-panel {
            min-height: 520px;
            padding: 16px;
            border-radius: 28px;
            box-shadow: 0 28px 70px rgba(0, 0, 0, 0.38);
        }
        .dashboard-panel::before { transform: rotate(-4deg); }
        .dashboard-content {
            padding: clamp(20px, 2vw, 28px);
            border-radius: 22px;
        }
        .stat-box, .request-item {
            border-color: rgba(100, 116, 139, 0.28);
            border-radius: 15px;
        }
        .request-list { gap: 12px; }
        .floating-card { right: 10px; bottom: 14px; }

        .features { padding: 10px 0 88px; }
        .section-head { display: block; margin-bottom: 30px; }
        .section-head p { max-width: none; width: 100%; margin-top: 14px; }
        .section-head h2 { line-height: 1.1; }
        .feature-grid, .steps-grid { gap: 18px; }
        .feature-card, .step-card {
            border-radius: 20px;
            background: rgba(15, 23, 42, 0.76);
            border-color: rgba(71, 85, 105, 0.48);
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.12);
        }
        .feature-card { padding: 26px 24px; }
        .feature-icon-box { border-radius: 14px; }
        .steps { padding-bottom: 88px; }
        footer { border-top-color: rgba(71, 85, 105, 0.42); }

        @media (max-width: 980px) {
            .hero { padding: 56px 0 64px; }
            .feature-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .section-head { align-items: flex-start; }
        }

        @media (max-width: 640px) {
            .nav { height: 68px; }
            .brand { font-size: 0.94rem; }
            .brand-mark { width: 38px; height: 38px; }
            .login-btn { padding: 10px 13px; }
            .hero { gap: 30px; padding: 42px 0 52px; }
            h1 { font-size: clamp(2.55rem, 12vw, 3.5rem); }
            .lead { font-size: 1rem; }
            .meta-row { gap: 12px; }
            .dashboard-panel { padding: 10px; border-radius: 22px; }
            .dashboard-content { padding: 16px; }
            .feature-grid { grid-template-columns: 1fr; }
            .section-head { margin-bottom: 22px; }
            .section-head h2 { margin-bottom: 12px; }
            .features { padding-bottom: 56px; }
            .steps { padding-bottom: 56px; }
        }        /* Keep the hero dashboard clean and prevent decorative layers overlapping its content. */
        .dashboard-panel::before { display: none; }
        .dashboard-panel {
            background: linear-gradient(145deg, rgba(24, 34, 56, 0.98), rgba(11, 18, 36, 0.98));
            border: 1px solid rgba(129, 140, 248, 0.26);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.42), 0 0 60px rgba(99, 102, 241, 0.08);
        }
        .floating-card {
            position: relative;
            inset: auto;
            width: fit-content;
            max-width: 100%;
            margin: 12px 4px 0 auto;
            box-shadow: none;
            background: rgba(30, 41, 59, 0.82);
            border-color: rgba(100, 116, 139, 0.34);
        }    </style>
</head>
<body>
    <header class="topbar">
        <div class="container nav">
            <a class="brand" href="index.php" aria-label="AKK SOLUTIONS home">
                <span class="brand-mark">AK</span>
                <span>AKK <span style="color:#818cf8;">SOLUTIONS</span></span>
            </a>

            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#process">How it works</a>
                <a href="login.php" class="login-btn"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
            </div>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div>
                <div class="eyebrow"><span class="dot"></span> Automated Management System</div>
                <h1>Automated leave management for <span class="highlight">better teams.</span></h1>
                <p class="lead">
                    A complete management system for leave requests, shift coverage, approvals,
                    employee records, and real-time administrative visibility.
                </p>

                <div class="cta-row">
                    <a href="#features" class="secondary-btn"><i class="fa-solid fa-circle-info"></i> View capabilities</a>
                </div>

                <div class="meta-row">
                    <div class="meta-item"><i class="fa-solid fa-check"></i> Smart auto-approval</div>
                    <div class="meta-item"><i class="fa-solid fa-check"></i> Shift protection</div>
                    <div class="meta-item"><i class="fa-solid fa-check"></i> Admin dashboard</div>
                </div>
            </div>

        </section>

        <section id="features" class="features">
            <div class="section-head">
                <h2>One system. Complete control.</h2>
                <p>Purpose-built tools that keep employees, managers, and administrators aligned throughout the leave process.</p>
            </div>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon-box"><i class="fa-solid fa-bolt"></i></div>
                    <h3>Smart Auto-Approval</h3>
                    <p>Real-time rule validation handles eligible requests quickly and sends exceptions for review.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon-box"><i class="fa-solid fa-calendar-days"></i></div>
                    <h3>Shift Coverage Protection</h3>
                    <p>Maintain minimum staff coverage across shifts while reviewing leave requests.</p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon-box"><i class="fa-solid fa-shield-heart"></i></div>
                    <h3>Admin Dashboard</h3>
                    <p>Manage calendars, employee accounts, approvals, rules, labs, riders, and activity logs in one place.</p>
                </article>
            </div>
        </section>

        <section id="process" class="steps">
            <div class="section-head">
                <h2>How it works</h2>
                <p>Simple steps from request to resolution so employees feel informed and teams stay covered.</p>
            </div>

            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h4>Request</h4>
                    <p>Employees submit leave requests with dates, reason, and shift details in seconds.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">2</div>
                    <h4>Review</h4>
                    <p>Rules and staff coverage are checked automatically so exceptions are highlighted early.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">3</div>
                    <h4>Approve</h4>
                    <p>Managers can approve or review requests quickly without chasing paperwork or messages.</p>
                </div>

                <div class="step-card">
                    <div class="step-number">4</div>
                    <h4>Track</h4>
                    <p>Everything remains visible in clear records, calendars, and team activity history.</p>
                </div>
            </div>
        </section>


    </main>

    <footer>
        <div class="container footer-row">
            <div>© <?= date('Y'); ?> AKK Solutions</div>
            <div>Automated Management System</div>
        </div>
    </footer>
</body>
</html>
