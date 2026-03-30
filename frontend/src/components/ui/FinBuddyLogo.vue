<script setup>
defineProps({
  width: { type: [Number, String], default: '100%' },
})
</script>

<template>
  <svg
    :width="width"
    height="auto"
    viewBox="128 118 432 188"
    xmlns="http://www.w3.org/2000/svg"
    aria-label="Fin Buddy"
    role="img"
    style="display: block;"
  >
    <defs>
      <linearGradient id="coinGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#FFD166"/>
        <stop offset="100%" stop-color="#F0A500"/>
      </linearGradient>
    </defs>

    <circle cx="220" cy="210" r="88" fill="#1D8058" opacity="0.25"/>
    <circle cx="220" cy="210" r="74" fill="#1D8058" opacity="0.35"/>

    <circle cx="220" cy="210" r="60" fill="#1D8058"/>

    <path d="M197 190 Q205 185 213 190" fill="none" stroke="#4DD99B" stroke-width="2.5" stroke-linecap="round"/>
    <path d="M227 190 Q235 185 243 190" fill="none" stroke="#4DD99B" stroke-width="2.5" stroke-linecap="round"/>

    <circle cx="205" cy="200" r="5" fill="#4DD99B"/>
    <circle cx="235" cy="200" r="5" fill="#4DD99B"/>

    <path d="M198 228 Q220 248 242 228" fill="none" stroke="#4DD99B" stroke-width="3.5" stroke-linecap="round"/>

    <circle cx="268" cy="153" r="22" fill="url(#coinGrad)"/>
    <circle cx="268" cy="153" r="16" fill="none" stroke="#F0A500" stroke-width="1.5"/>
    <text x="268" y="159" text-anchor="middle" font-family="Arial Black, Arial, sans-serif" font-size="16" font-weight="900" fill="#7A4800">$</text>

    <rect x="134" y="228" width="8" height="20" rx="2" fill="#4DD99B" opacity="0.5"/>
    <rect x="146" y="218" width="8" height="30" rx="2" fill="#4DD99B" opacity="0.7"/>
    <rect x="158" y="206" width="8" height="42" rx="2" fill="#4DD99B"/>

    <circle cx="330" cy="183" r="6" fill="#4DD99B"/>
    <circle cx="330" cy="257" r="6" fill="#FFD166"/>

    <text x="340" y="188" font-family="Arial Black, Arial, sans-serif" font-size="72" font-weight="900" fill="#0F4C35" letter-spacing="-2">fin</text>

    <text x="340" y="262" font-family="Arial Black, Arial, sans-serif" font-size="72" font-weight="900" fill="#4DD99B" letter-spacing="-2">buddy</text>
  </svg>
</template>
