# UI Design System & Tokens (`DESIGN.md`)

Single source of truth for the minimal, calm, consistent visual design across all pages.

---

## 1. Design Tokens

### Colours
- **Page Background**: `bg-slate-50` (`#f8fafc`) | Dark: `dark:bg-[#0f1220]`
- **Surface (Cards / Panels / Sidebar / Topbar)**: `bg-white` (`#ffffff`) | Dark: `dark:bg-[#171b2e]`
- **Border / Ring**: `ring-1 ring-slate-200/70` (`border-slate-200/70`) | Dark: `dark:ring-slate-700/60`
- **Text**:
  - Primary: `text-slate-800` (`dark:text-slate-100`)
  - Secondary: `text-slate-500` (`dark:text-slate-400`)
  - Disabled / Muted: `text-slate-400` (`dark:text-slate-500`)
- **Primary Accent**: `bg-orange-500` (`#f97316`), hover `hover:bg-orange-600` (`#ea580c`), soft tint `bg-orange-50 text-orange-600`
- **Semantic Tones**:
  - Success: `emerald` (`bg-emerald-50 text-emerald-700 ring-emerald-200/60`)
  - Warning: `amber` (`bg-amber-50 text-amber-700 ring-amber-200/60`)
  - Danger: `rose` (`bg-rose-50 text-rose-700 ring-rose-200/60`)
  - Info: `sky` (`bg-sky-50 text-sky-700 ring-sky-200/60`)
  - Neutral: `slate` (`bg-slate-100 text-slate-600 ring-slate-200/60`)

### Stat Card Gradients (Semantic Consistency)
- `purple` (Users / Customers): `bg-gradient-to-br from-violet-600 to-purple-700`
- `blue` (Sales / Invoices / Network): `bg-gradient-to-br from-sky-500 to-blue-600`
- `orange` (Hotspot / Vouchers / Active): `bg-gradient-to-br from-amber-500 to-orange-600`
- `green` (Money / Revenue / Paid / Online): `bg-gradient-to-br from-emerald-500 to-green-600`
- `navy` (Resellers / System / Infrastructure): `bg-gradient-to-br from-slate-800 to-[#1e1b3a]`
- `pink` (Commissions / Expenses / Alerts): `bg-gradient-to-br from-pink-500 to-rose-600`

### Typography
- **Font Family**: Inherited system/app sans-serif (`Jost`, `-apple-system`, `BlinkMacSystemFont`, `Segoe UI`, `Roboto`, `sans-serif`)
- **Page Title**: `text-xl font-semibold tracking-tight text-slate-800 dark:text-slate-100`
- **Page Subtitle**: `text-xs text-slate-500 mt-1`
- **Section Title**: `text-sm font-semibold text-slate-800 dark:text-slate-100`
- **Body Text**: `text-sm text-slate-700 dark:text-slate-300`
- **Helper / Label**: `text-xs font-medium text-slate-600 dark:text-slate-400`
- **Micro Text**: `text-[11px] text-slate-500`
- **Big Stat Numbers**: `text-2xl font-semibold tabular-nums` (never larger than `text-2xl`)
- **Casing**: Sentence case everywhere (no `uppercase` or wide letter-spacing on labels/headers).

### Spacing & Shape
- **Page Wrapper**: `mx-auto max-w-[1400px] px-4 sm:px-6 py-6 space-y-6`
- **Card**: `rounded-2xl p-5 bg-white dark:bg-[#171b2e] ring-1 ring-slate-200/70 dark:ring-slate-700/60 shadow-none`
- **Inner Items**: `rounded-xl`
- **Buttons & Inputs**: `rounded-lg`
- **Pills & Badges**: `rounded-full`
- **Grid Gaps**: `gap-4`
- **Section Vertical Spacing**: `space-y-6`

