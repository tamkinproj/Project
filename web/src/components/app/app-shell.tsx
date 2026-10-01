"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { Bell, Home, IdCard, LogOut, MailWarning, Search, Settings, ShieldCheck, User } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";

import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Logo, LogoMark } from "@/components/ui/logo";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { cx } from "@/lib/cx";
import { signOut, useMe } from "@/lib/session";

type NavItem = { href: string; label: string; icon: typeof Home; badge?: number };

function useUnreadCount() {
  return useQuery({
    queryKey: ["notifications", "unread"],
    queryFn: () => api<{ unread: number; security_unread: number }>("notifications/unread-count"),
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  });
}

function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

function VerifyBanner() {
  const toast = useToast();
  const resend = useMutation({
    mutationFn: () => api<{ message: string }>("auth/email/resend", { method: "POST" }),
    onSuccess: (data) => toast.success(data.message),
    onError: (error) => toast.error(errorMessage(error)),
  });

  return (
    <div className="mb-4 flex flex-col gap-3 rounded-2xl bg-warning-soft px-4 py-3 text-sm text-warning sm:flex-row sm:items-center sm:justify-between">
      <p className="flex items-center gap-2">
        <MailWarning className="size-4 shrink-0" aria-hidden />
        Verify your email to post, comment and follow people. Check your inbox for the link.
      </p>
      <Button size="sm" variant="outline" loading={resend.isPending} onClick={() => resend.mutate()}>
        Resend email
      </Button>
    </div>
  );
}

export function AppShell({ children }: { children: ReactNode }) {
  const me = useMe();
  const pathname = usePathname();
  const { data: unread } = useUnreadCount();
  const isAdmin = me.permissions.length > 0;

  const nav: NavItem[] = [
    { href: "/home", label: "Home", icon: Home },
    { href: "/search", label: "Search", icon: Search },
    { href: "/notifications", label: "Notifications", icon: Bell, badge: unread?.unread },
    { href: "/card", label: "My card", icon: IdCard },
    { href: `/u/${me.profile.username}`, label: "Profile", icon: User },
    { href: "/settings", label: "Settings", icon: Settings },
  ];

  const mobileNav: NavItem[] = [nav[0], nav[1], nav[3], { ...nav[2], label: "Alerts" }, { ...nav[4], label: "Me" }];

  return (
    <div className="mx-auto flex min-h-dvh max-w-6xl">
      <aside className="sticky top-0 hidden h-dvh w-64 shrink-0 flex-col justify-between border-r border-border px-4 py-6 lg:flex">
        <div>
          <Link href="/home" className="mb-8 block px-3">
            <Logo />
          </Link>
          <nav aria-label="Main" className="space-y-1">
            {nav.map((item) => (
              <SidebarLink key={item.href} item={item} active={isActive(pathname, item.href)} />
            ))}
            {isAdmin && <SidebarLink item={{ href: "/admin", label: "Administration", icon: ShieldCheck }} active={isActive(pathname, "/admin")} />}
          </nav>
        </div>

        <div className="flex items-center gap-3 rounded-2xl p-2">
          <Avatar name={me.profile.display_name} src={me.profile.avatar_url} size="sm" />
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold">{me.profile.display_name}</p>
            <p className="truncate text-xs text-muted">@{me.profile.username}</p>
          </div>
          <button type="button" onClick={signOut} className="rounded-full p-2 text-muted hover:bg-surface-muted hover:text-text" aria-label="Sign out" title="Sign out">
            <LogOut className="size-4" />
          </button>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="sticky top-0 z-20 flex items-center justify-between border-b border-border bg-bg/85 px-4 py-2.5 backdrop-blur lg:hidden">
          <Link href="/home" aria-label="Home">
            <LogoMark className="size-7" />
          </Link>
          <div className="flex items-center gap-1">
            {isAdmin && (
              <Link href="/admin" className="rounded-full p-2 text-muted hover:bg-surface-muted" aria-label="Administration">
                <ShieldCheck className="size-5" />
              </Link>
            )}
            <Link href="/settings" className="rounded-full p-2 text-muted hover:bg-surface-muted" aria-label="Settings">
              <Settings className="size-5" />
            </Link>
          </div>
        </header>

        <main className={cx("mx-auto w-full flex-1 px-4 pb-28 pt-5 sm:px-6 lg:pb-10 lg:pt-8", pathname.startsWith("/admin") ? "max-w-5xl" : "max-w-2xl")}>
          {!me.email_verified && <VerifyBanner />}
          {children}
        </main>
      </div>

      <nav aria-label="Main" className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden">
        <ul className="mx-auto flex max-w-md justify-around">
          {mobileNav.map((item) => {
            const active = isActive(pathname, item.href);
            const Icon = item.icon;
            return (
              <li key={item.href}>
                <Link href={item.href} aria-current={active ? "page" : undefined} className={cx("relative flex flex-col items-center gap-0.5 px-3 py-2 text-[11px] font-medium", active ? "text-brand" : "text-muted")}>
                  <Icon className="size-[22px]" strokeWidth={active ? 2.4 : 2} aria-hidden />
                  {item.label}
                  {!!item.badge && <span className="absolute right-2 top-1 min-w-4 rounded-full bg-danger px-1 text-center text-[10px] leading-4 text-white">{item.badge > 99 ? "99+" : item.badge}</span>}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
    </div>
  );
}

function SidebarLink({ item, active }: { item: NavItem; active: boolean }) {
  const Icon = item.icon;

  return (
    <Link
      href={item.href}
      aria-current={active ? "page" : undefined}
      className={cx(
        "flex items-center gap-3.5 rounded-2xl px-3 py-2.5 text-[15px] transition-colors",
        active ? "bg-surface font-semibold text-text shadow-card" : "text-muted hover:bg-surface-muted hover:text-text",
      )}
    >
      <Icon className={cx("size-5", active && "text-brand")} strokeWidth={active ? 2.4 : 2} aria-hidden />
      <span className="flex-1">{item.label}</span>
      {!!item.badge && <span className="rounded-full bg-danger px-2 py-0.5 text-xs font-semibold text-white">{item.badge > 99 ? "99+" : item.badge}</span>}
    </Link>
  );
}
