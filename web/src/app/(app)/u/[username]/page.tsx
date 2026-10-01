import { ProfileView } from "@/components/app/profile-view";

export async function generateMetadata({ params }: PageProps<"/u/[username]">) {
  const { username } = await params;
  return { title: `@${decodeURIComponent(username)}` };
}

export default async function ProfilePage({ params }: PageProps<"/u/[username]">) {
  const { username } = await params;
  return <ProfileView key={username} username={decodeURIComponent(username)} />;
}
