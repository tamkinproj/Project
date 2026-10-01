import { Suspense } from "react";

import { FeedView } from "@/components/app/feed-view";

export const metadata = { title: "Home" };

export default function HomePage() {
  return (
    <Suspense>
      <FeedView />
    </Suspense>
  );
}
