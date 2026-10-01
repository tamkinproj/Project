"use client";

import { useMutation } from "@tanstack/react-query";

import { SelectField, Toggle } from "@/components/ui/field";
import { Panel } from "@/components/ui/misc";
import { useToast } from "@/components/ui/toast";
import { api, errorMessage } from "@/lib/api";
import { useMe, useRefreshMe } from "@/lib/session";
import type { Privacy } from "@/lib/types";

export function PrivacySettings() {
  const me = useMe();
  const refreshMe = useRefreshMe();
  const toast = useToast();

  const update = useMutation({
    mutationFn: (changes: Partial<Privacy>) => api<{ data: Privacy }>("me/privacy", { method: "PATCH", json: changes }),
    onSuccess: ({ data }) => {
      refreshMe({ ...me, privacy: data });
      toast.success("Privacy updated.");
    },
    onError: (error) => toast.error(errorMessage(error)),
  });

  const privacy = me.privacy;

  return (
    <div className="space-y-5">
      <Panel className="divide-y divide-border px-5 sm:px-6">
        <Toggle
          label="Appear in search"
          description="Let people find you by name or username."
          checked={privacy.profile_searchable}
          disabled={update.isPending}
          onChange={(value) => update.mutate({ profile_searchable: value })}
        />
        <Toggle
          label="Show location on profile"
          description="Only applies if you've added a location."
          checked={privacy.show_location}
          disabled={update.isPending}
          onChange={(value) => update.mutate({ show_location: value })}
        />
      </Panel>

      <Panel className="space-y-4 p-5 sm:p-6">
        <SelectField
          label="Default audience for new posts"
          hint="You can still choose per post."
          value={privacy.default_post_visibility}
          onChange={(e) => update.mutate({ default_post_visibility: e.target.value as Privacy["default_post_visibility"] })}
        >
          <option value="public">Public — anyone on the platform</option>
          <option value="followers">Followers only</option>
          <option value="only_me">Only me</option>
        </SelectField>
        <SelectField label="Who can follow you" value={privacy.who_can_follow} onChange={(e) => update.mutate({ who_can_follow: e.target.value as Privacy["who_can_follow"] })}>
          <option value="everyone">Everyone</option>
          <option value="nobody">No one</option>
        </SelectField>
        <SelectField
          label="Who can message you"
          hint="Applies when messaging becomes available."
          value={privacy.who_can_message}
          onChange={(e) => update.mutate({ who_can_message: e.target.value as Privacy["who_can_message"] })}
        >
          <option value="everyone">Everyone</option>
          <option value="followers">Your followers</option>
          <option value="nobody">No one</option>
        </SelectField>
      </Panel>

      <p className="px-1 text-sm text-muted">
        These settings are enforced by our servers, not just hidden in the app. Your email, phone, legal name and date of birth are never public.
      </p>
    </div>
  );
}
