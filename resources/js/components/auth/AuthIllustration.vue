<template>
    <svg viewBox="0 0 520 520" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <filter id="auth-illustration-glow" x="-50%" y="-50%" width="200%" height="200%">
                <feGaussianBlur stdDeviation="45" />
            </filter>
        </defs>

        <!-- soft color glows -->
        <circle cx="120" cy="120" r="140" fill="#02CD86" opacity="0.12" filter="url(#auth-illustration-glow)" />
        <circle cx="430" cy="400" r="150" fill="#6C4EE9" opacity="0.14" filter="url(#auth-illustration-glow)" />

        <!-- orbit ring -->
        <circle cx="260" cy="250" r="215" fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1" stroke-dasharray="2 10" />

        <!-- sparkles -->
        <circle cx="74" cy="200" r="3" fill="#ffffff" opacity="0.25" />
        <circle cx="452" cy="160" r="2.5" fill="#ffffff" opacity="0.3" />
        <circle cx="430" cy="430" r="2" fill="#ffffff" opacity="0.2" />
        <circle cx="96" cy="430" r="2.5" fill="#ffffff" opacity="0.25" />

        <!-- main dashboard card -->
        <rect x="70" y="80" width="380" height="300" rx="28" fill="#1f1f1f" stroke="rgba(255,255,255,0.08)" stroke-width="1.5" />

        <!-- header -->
        <circle cx="110" cy="120" r="14" fill="#02CD86" opacity="0.18" />
        <path d="M104 120l4 4 8-8" stroke="#02CD86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
        <rect x="134" y="112" width="92" height="10" rx="5" fill="#ffffff" opacity="0.9" />
        <rect x="134" y="128" width="60" height="8" rx="4" fill="#989898" opacity="0.6" />

        <rect x="378" y="104" width="52" height="24" rx="12" fill="#02CD86" opacity="0.15" />
        <text x="404" y="120" text-anchor="middle" font-size="11" font-weight="600" fill="#02CD86" font-family="Instrument Sans, sans-serif">+12%</text>

        <!-- KPI mini cards -->
        <g>
            <rect x="96" y="158" width="106" height="80" rx="16" fill="#262626" stroke="rgba(255,255,255,0.06)" />
            <circle cx="122" cy="184" r="14" fill="#0d2e22" />
            <path d="M116 187l6-8 4 4 6-8" stroke="#02CD86" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <rect x="112" y="206" width="60" height="8" rx="4" fill="#ffffff" opacity="0.85" />
            <rect x="112" y="220" width="40" height="6" rx="3" fill="#989898" opacity="0.5" />
        </g>

        <g>
            <rect x="212" y="158" width="106" height="80" rx="16" fill="#262626" stroke="rgba(255,255,255,0.06)" />
            <circle cx="238" cy="184" r="14" fill="#24212f" />
            <path d="M232 181l6 8 4-4 6 8" stroke="#6C4EE9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
            <rect x="228" y="206" width="60" height="8" rx="4" fill="#ffffff" opacity="0.85" />
            <rect x="228" y="220" width="40" height="6" rx="3" fill="#989898" opacity="0.5" />
        </g>

        <g>
            <rect x="328" y="158" width="106" height="80" rx="16" fill="#262626" stroke="rgba(255,255,255,0.06)" />
            <circle cx="354" cy="184" r="14" fill="#2a1f22" />
            <path d="M348 184h12M354 178v12" stroke="#E94E50" stroke-width="2" stroke-linecap="round" />
            <rect x="344" y="206" width="60" height="8" rx="4" fill="#ffffff" opacity="0.85" />
            <rect x="344" y="220" width="40" height="6" rx="3" fill="#989898" opacity="0.5" />
        </g>

        <!-- donut chart -->
        <g transform="rotate(-90 150 320)">
            <circle cx="150" cy="320" r="46" fill="none" stroke="#333333" stroke-width="16" />
            <circle cx="150" cy="320" r="46" fill="none" stroke="#02CD86" stroke-width="16" stroke-dasharray="130 159" stroke-linecap="round" />
            <circle cx="150" cy="320" r="46" fill="none" stroke="#6C4EE9" stroke-width="16" stroke-dasharray="87 202" stroke-dashoffset="-132" stroke-linecap="round" />
            <circle cx="150" cy="320" r="46" fill="none" stroke="#5a5a5a" stroke-width="16" stroke-dasharray="60 229" stroke-dashoffset="-221" stroke-linecap="round" />
        </g>
        <rect x="124" y="316" width="52" height="8" rx="4" fill="#ffffff" opacity="0.85" />

        <!-- bar chart -->
        <g>
            <rect x="234" y="320" width="20" height="46" rx="6" fill="#333333" />
            <rect x="264" y="296" width="20" height="70" rx="6" fill="#02CD86" />
            <rect x="294" y="334" width="20" height="32" rx="6" fill="#333333" />
            <rect x="324" y="284" width="20" height="82" rx="6" fill="#6C4EE9" />
            <rect x="354" y="310" width="20" height="56" rx="6" fill="#333333" />
            <rect x="384" y="302" width="20" height="64" rx="6" fill="#02CD86" />
            <line x1="234" y1="372" x2="404" y2="372" stroke="rgba(255,255,255,0.08)" stroke-width="1" />
        </g>

        <!-- floating coin badges -->
        <circle cx="448" cy="78" r="28" fill="#6C4EE9" />
        <text x="448" y="84" text-anchor="middle" font-size="20" font-weight="700" fill="#ffffff" font-family="Instrument Sans, sans-serif">$</text>

        <circle cx="64" cy="392" r="22" fill="#02CD86" />
        <text x="64" y="398" text-anchor="middle" font-size="16" font-weight="700" fill="#101010" font-family="Instrument Sans, sans-serif">$</text>
    </svg>
</template>
