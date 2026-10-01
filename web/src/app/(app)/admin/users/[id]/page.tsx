import { UserDetailView } from "@/components/admin/users";

export const metadata = { title: "Member" };

export default async function AdminUserPage({ params }: PageProps<"/admin/users/[id]">) {
  const { id } = await params;
  return <UserDetailView id={id} />;
}
