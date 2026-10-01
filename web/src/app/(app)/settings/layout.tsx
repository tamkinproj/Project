import { SettingsNav } from "@/components/app/settings/settings-nav";
import { PageHeader } from "@/components/ui/misc";

export default function SettingsLayout({ children }: LayoutProps<"/settings">) {
  return (
    <div>
      <PageHeader title="Settings" />
      <SettingsNav />
      {children}
    </div>
  );
}
