(function registerDiscordNotificationAction(wp, JetFBActions, actionData, jfb) {
  if (!wp || !JetFBActions || !JetFBActions.addAction) {
    return;
  }

  const { __ } = wp.i18n || { __: (str) => str };
  const { createElement, Fragment } = wp.element || {};
  const { TextareaControl } = wp.components || {};
  const { addFilter } = wp.hooks || {};

  if (!createElement || !Fragment || !TextareaControl) {
    return;
  }

  const jetFormBuilder = jfb || {};
  const jfbComponents = jetFormBuilder.components || {};
  const jetFBRegistry = window.JetFBComponents || {};

  const StyledTextarea = jfbComponents.StyledTextareaControl || TextareaControl;
  const HelpComponent = jfbComponents.Help || null;
  const RowControl = jfbComponents.RowControl || null;
  const LabelWithActions = jfbComponents.LabelWithActions || null;
  const LabelComponent = jfbComponents.Label || null;
  const MacrosFields = jetFBRegistry.MacrosFields || null;

  const labels = actionData.__labels || {};
  const helpMessages = actionData.__help_messages || {};

  const toStringValue = (value) => (typeof value === "string" ? value : "");

  const getLabel = (labelFn, key, fallback) => {
    if (typeof labelFn === "function") {
      try {
        const maybe = labelFn(key);
        if (maybe) {
          return maybe;
        }
      } catch (e) {
        // ignore
      }
    }

    if (labels[key]) {
      return labels[key];
    }

    return fallback;
  };

  const getHelp = (key, fallback) => {
    if (helpMessages[key]) {
      return helpMessages[key];
    }

    return fallback;
  };

  const DiscordNotification = (props) => {
    const { settings, onChangeSetting, label } = props;
    const message = toStringValue(settings.message);
    const includeRefer = !!settings.include_refer;
    const includeForm = !!settings.include_form;

    const messageLabel = getLabel(
      label,
      "message",
      __("Discord message", "discord-for-jetformbuilder")
    );

    const messageHelp = getHelp(
      "message",
      __(
        "Content that will be posted to Discord. You can use JetFormBuilder macros.",
        "discord-for-jetformbuilder"
      )
    );

    const includeReferLabel = getLabel(
      label,
      "include_refer",
      __("Include refer URL", "discord-for-jetformbuilder")
    );

    const includeReferHelp = getHelp(
      "include_refer",
      __(
        "Append the form refer URL to the Discord message.",
        "discord-for-jetformbuilder"
      )
    );

    const updateMessage = (value) => {
      onChangeSetting(value, "message");
    };

    const switcherControl =
      jfbComponents.SwitcherControl || wp.components?.ToggleControl || null;

    const includeReferControl = switcherControl
      ? createElement(switcherControl, {
          label: includeReferLabel,
          help: includeReferHelp,
          checked: includeRefer,
          onChange: (value) => onChangeSetting(!!value, "include_refer"),
        })
      : null;

    const includeFormLabel = getLabel(
      label,
      "include_form",
      __("Include form name", "discord-for-jetformbuilder")
    );

    const includeFormHelp = getHelp(
      "include_form",
      __(
        "Append the JetFormBuilder form name to the Discord message.",
        "discord-for-jetformbuilder"
      )
    );

    const includeFormControl = switcherControl
      ? createElement(switcherControl, {
          label: includeFormLabel,
          help: includeFormHelp,
          checked: includeForm,
          onChange: (value) => onChangeSetting(!!value, "include_form"),
        })
      : null;

    if (
      RowControl &&
      LabelWithActions &&
      LabelComponent &&
      MacrosFields
    ) {
      return createElement(
        Fragment,
        null,
        createElement(
          RowControl,
          null,
          ({ id }) =>
            createElement(
              Fragment,
              null,
              createElement(
                LabelWithActions,
                null,
                createElement(LabelComponent, { htmlFor: id }, messageLabel),
                createElement(MacrosFields, {
                  withCurrent: true,
                  onClick: (macro) => updateMessage(`${message}${macro}`),
                })
              ),
              createElement(StyledTextarea, {
                id,
                value: message,
                help: messageHelp,
                onChange: updateMessage,
                rows: 5,
                __nextHasNoMarginBottom: true,
                __next40pxDefaultSize: true,
              })
            )
        ),
        includeReferControl,
        includeFormControl
      );
    }

    return createElement(
      Fragment,
      null,
      createElement(StyledTextarea, {
        label: messageLabel,
        help: messageHelp,
        value: message,
        onChange: updateMessage,
      }),
      includeReferControl,
      includeFormControl
    );
  };

  JetFBActions.addAction("discord_notification", DiscordNotification, {
    category: "communication",
    docHref: "https://github.com/Lonsdale201/discord-for-jetformbuilder",
  });

  if (wp?.data?.dispatch) {
    try {
      wp.data.dispatch("jet-forms/actions").registerAction({
        type: "discord_notification",
        label:
          (actionData && actionData.action_name) ||
          __("Discord notification", "discord-for-jetformbuilder"),
        category: "communication",
        docHref: "https://github.com/Lonsdale201/discord-for-jetformbuilder",
      });
    } catch (error) {
      // noop
    }
  }

  if (addFilter) {
    addFilter(
      "jet.fb.actions.dropdown",
      "discord-for-jetformbuilder/action-title",
      (options = []) => {
        const exists = options.some(
          (option) => option && option.value === "discord_notification"
        );

        if (exists) {
          return options;
        }

        return [
          ...options,
          {
            value: "discord_notification",
            label: __("Discord notification", "discord-for-jetformbuilder"),
          },
        ];
      }
    );
  }
})(
  window.wp || false,
  window.JetFBActions || false,
  window.DiscordNotification || {},
  window.jfb || {}
);
