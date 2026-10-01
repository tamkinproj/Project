import { Suspense } from "react";

import { CardsAdminView } from "@/components/admin/cards";

export const metadata = { title: "Cards" };

export default function AdminCardsPage() {
  return (
    <Suspense>
      <CardsAdminView />
    </Suspense>
  );
}
