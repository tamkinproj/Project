import { notFound } from "next/navigation";

import { AdminNav } from "@/components/admin/admin-nav";
import { getMe } from "@/lib/server/session";

export const metadata = { title: { default: "Administration", template: "%s · Administration" } };

export default async function AdminLayout({ children }: LayoutProps<"/admin">) {
  const me = await getMe();

  // Hides the area from non-staff. Every admin API endpoint still enforces its own permission.
  if (!me || me.permissions.length === 0) notFound();

  return (
    <div>
      <div className="mb-4 flex items-baseline justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">Administration</h1>
        <span className="text-xs text-muted">All actions are recorded in the audit log</span>
      </div>
      <AdminNav />
      {children}
    </div>
  );
}
