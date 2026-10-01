import { Suspense } from "react";

import { UsersView } from "@/components/admin/users";

export const metadata = { title: "Users" };

export default function AdminUsersPage() {
  return (
    <Suspense>
      <UsersView />
    </Suspense>
  );
}
