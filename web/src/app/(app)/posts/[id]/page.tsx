import { PostView } from "@/components/app/post-view";

export const metadata = { title: "Post" };

export default async function PostPage({ params }: PageProps<"/posts/[id]">) {
  const { id } = await params;
  return <PostView id={id} />;
}
