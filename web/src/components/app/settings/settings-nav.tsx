"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

import { cx } from "@/lib/cx";

const tabs = [
  { href: "/settings", label: "Profile" },
  { href: "/settings/privacy", label: "Privacy" },
  { href: "/settings/personal", label: "Personal info" },
  { href: "/settings/security", label: "Security" },
  { href: "/settings/account", label: "Account" },
];

export function SettingsNav() {
  const pathname = usePathname();

  return (
    <nav aria-label="Settings" className="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
      <ul className="flex gap-1.5">
        {tabs.map((tab) => {
          const active = pathname === tab.href;
          return (
            <li key={tab.href}>
              <Link
                href={tab.href}
                aria-current={active ? "page" : undefined}
                className={cx("block whitespace-nowrap rounded-full px-4 py-2 text-sm font-medium transition-colors", active ? "bg-text text-bg" : "bg-surface text-muted hover:text-text")}
              >
                {tab.label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
