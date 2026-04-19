# AGENTS.md — DeadCenter

This file is the entry point for any AI agent (Cursor, subagents, CI assistants) working in this repo. Read it before you do anything else.

## 1. Binding UX/UI/IA standard

**All UX, UI, layout, navigation, information-architecture, and interaction work in this repo is governed by the DeadCenter UX Standard:**

➡️ [.cursor/rules/deadcenter-ux-standard.mdc](.cursor/rules/deadcenter-ux-standard.mdc)

That rule is `alwaysApply: true` — Cursor will surface it automatically in every chat. Before building or refactoring any screen, component, or flow, interpret the task through that standard. The ten UX principles (§1), mode-based design (§2), page template (§4), match UX (§5), dashboard rules (§6), table/form rules (§7–8), component primitives (§14), and design QA checklist (§15) are non-negotiable unless a later instruction explicitly overrides a specific part.

**Do not break** existing functionality, routes, permissions, middleware, role logic, data models, or the match lifecycle — improve the experience layer on top of them.

## 2. Architecture quick-facts

- Laravel 13 + Livewire/Volt + Blade + Tailwind + Flux.
- Domain-aware: `app`, `md`, `shooter` hosts resolved by `DomainContext` middleware.
- Three authenticated modes: **Shooter** (default), **Organization** (if user owns an org), **Platform Admin** (`users.role` in `owner`, `match_director`).
- Mode switcher is session-backed via `user_available_modes()` / `user_home_path()` in `app/Helpers/roles.php` and `ModeSwitchController`.
- Shared UI primitives live under `resources/views/components/`. Reuse before inventing (see `.cursor/rules/deadcenter-ux-standard.mdc` §14 for the canonical list).

## 3. Before finishing any task

Run the design QA checklist from the UX standard (§15). If any answer is "no", keep refining.
