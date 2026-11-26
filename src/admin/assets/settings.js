/**
 * Block Templates > Settings: who may create, edit, publish and delete templates, which extra columns
 * the template lists show, and whether the walkthrough is offered.
 *
 * Replaces the plain form that the PHP side (Settings::render_site_page) prints as the fallback without
 * JavaScript, and saves through the REST settings endpoint. On a multisite network the same page edits
 * the network default under Network Admin > Settings > Block Editor Templates
 * (Settings::render_network_page), saving through the plugin's own network settings route.
 *
 * The page state lives in one reducer. Every setting row is a memoized component that gets the same
 * onChange callback, so typing in one role search or flipping one switch only renders that row.
 */

/**
 * Import WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardDivider,
	CardFooter,
	CardHeader,
	CheckboxControl,
	Notice,
	SearchControl,
	ToggleControl,
} from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import {
	createRoot,
	Fragment,
	memo,
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import './settings.scss';

/**
 * Whether two setting values are the same: booleans, or lists of roles.
 *
 * @param {boolean|string[]} a A value.
 * @param {boolean|string[]} b Another value.
 *
 * @return {boolean} True when they are the same.
 */
const sameValue = (a, b) =>
	Array.isArray(a) && Array.isArray(b)
		? a.length === b.length && a.every((item, index) => item === b[index])
		: a === b;

/**
 * The page state: the values being edited, whether the site follows the network, what was saved
 * last, and the save in progress or its outcome.
 *
 * @param {Object} state  The state.
 * @param {Object} action The action: CHANGE, SET_USE_NETWORK, SAVE_START, SAVE_SUCCESS, SAVE_ERROR
 *                        or DISMISS_NOTICE.
 *
 * @return {Object} The new state.
 */
const reducer = (state, action) => {
	switch (action.type) {
		case 'CHANGE':
			return sameValue(state.values[action.key], action.value)
				? state
				: {
						...state,
						values: { ...state.values, [action.key]: action.value },
					};
		case 'SET_USE_NETWORK':
			return { ...state, useNetwork: action.useNetwork };
		case 'SAVE_START':
			return { ...state, busy: true, notice: null };
		case 'SAVE_SUCCESS':
			return {
				...state,
				busy: false,
				// What the server stored, which may differ from what was sent (a role
				// that cannot be picked is dropped), so the page shows the truth.
				values: action.values,
				saved: { values: action.values, useNetwork: action.useNetwork },
				notice: {
					status: 'success',
					message: __('Settings saved.', 'block-editor-templates'),
				},
			};
		case 'SAVE_ERROR':
			return {
				...state,
				busy: false,
				notice: { status: 'error', message: action.message },
			};
		case 'DISMISS_NOTICE':
			return { ...state, notice: null };
		default:
			return state;
	}
};

/**
 * One group of role checkboxes.
 *
 * @param {Object}                                   props          Component props.
 * @param {string}                                   props.title    The group title.
 * @param {Object[]}                                 props.options  The roles: value and label.
 * @param {string[]}                                 props.chosen   The chosen roles.
 * @param {(role: string, checked: boolean) => void} props.onToggle Called when a role is ticked or not.
 *
 * @return {Element} The group.
 */
const RoleGroup = memo(({ title, options, chosen, onToggle }) => (
	<fieldset className="abet-role-picker__group">
		<legend>{title}</legend>
		{options.map(({ value, label }) => {
			const checked = chosen.includes(value);
			return (
				<CheckboxControl
					key={value}
					__nextHasNoMarginBottom
					label={label}
					checked={checked}
					// The last chosen role stays: a setting always has one.
					disabled={checked && chosen.length === 1}
					onChange={(isChecked) => onToggle(value, isChecked)}
				/>
			);
		})}
	</fieldset>
));

/**
 * Choose one or more minimum roles: the chosen ones as a sentence beside a button that opens a list
 * of checkboxes, with a search field and the WordPress and custom roles in groups of their own. A
 * disclosure rather than a <select multiple>, which is hard to use with a mouse and worse by keyboard.
 *
 * @param {Object}                                 props            Component props.
 * @param {string}                                 props.settingKey The setting key.
 * @param {string}                                 props.label      The label.
 * @param {string}                                 props.help       The description.
 * @param {string[]}                               props.value      The chosen roles.
 * @param {Object[]}                               props.roles      The roles: value, label and group.
 * @param {Object}                                 props.groups     The group titles, by group, in display order.
 * @param {boolean}                                props.disabled   Whether the roles cannot be changed.
 * @param {(key: string, value: string[]) => void} props.onChange   Called with the setting key and the new roles.
 *
 * @return {Element} The picker.
 */
const RolePicker = memo(
	({ settingKey, label, help, value, roles, groups, disabled, onChange }) => {
		const [isOpen, setIsOpen] = useState(false);
		const [search, setSearch] = useState('');
		const id = `abet-setting-${settingKey}`;
		const chosen = useMemo(() => value ?? [], [value]);

		const labels = useMemo(
			() => new Map(roles.map((role) => [role.value, role.label])),
			[roles]
		);

		const names = useMemo(
			() => chosen.map((role) => labels.get(role) ?? role).join(', '),
			[chosen, labels]
		);

		// The roles per group that match the search.
		const grouped = useMemo(() => {
			const term = search.trim().toLowerCase();
			return Object.entries(groups)
				.map(([group, title]) => ({
					group,
					title,
					options: roles.filter(
						(role) =>
							role.group === group &&
							(!term ||
								role.label.toLowerCase().includes(term) ||
								role.value.includes(term))
					),
				}))
				.filter(({ options }) => options.length);
		}, [roles, groups, search]);

		const onToggle = useCallback(
			(role, checked) =>
				onChange(
					settingKey,
					checked
						? [...chosen, role]
						: chosen.filter((item) => item !== role)
				),
			[chosen, onChange, settingKey]
		);

		const toggleOpen = useCallback(() => setIsOpen((open) => !open), []);

		return (
			<fieldset
				className="abet-role-picker"
				aria-describedby={`${id}-help`}
			>
				<legend className="abet-role-picker__label">{label}</legend>
				<div className="abet-role-picker__summary">
					<p className="abet-role-picker__chosen">{names}</p>
					<Button
						variant="secondary"
						size="compact"
						aria-expanded={isOpen}
						aria-controls={`${id}-roles`}
						disabled={disabled}
						accessibleWhenDisabled
						onClick={toggleOpen}
					>
						{isOpen
							? __('Done', 'block-editor-templates')
							: __('Choose roles', 'block-editor-templates')}
					</Button>
				</div>
				{isOpen && !disabled && (
					<div id={`${id}-roles`} className="abet-role-picker__panel">
						<SearchControl
							__nextHasNoMarginBottom
							label={__('Search roles', 'block-editor-templates')}
							value={search}
							onChange={setSearch}
						/>
						{grouped.map(({ group, title, options }) => (
							<RoleGroup
								key={group}
								title={title}
								options={options}
								chosen={chosen}
								onToggle={onToggle}
							/>
						))}
						{!grouped.length && (
							<p>
								{__(
									'No roles found.',
									'block-editor-templates'
								)}
							</p>
						)}
					</div>
				)}
				<p id={`${id}-help`} className="components-base-control__help">
					{help}
				</p>
			</fieldset>
		);
	}
);

/**
 * A setting that is on or off.
 *
 * @param {Object}                                props            Component props.
 * @param {string}                                props.settingKey The setting key.
 * @param {string}                                props.label      The label.
 * @param {string}                                props.help       The description.
 * @param {boolean}                               props.checked    Whether it is on.
 * @param {boolean}                               props.disabled   Whether it cannot be changed.
 * @param {(key: string, value: boolean) => void} props.onChange   Called with the setting key and the new value.
 *
 * @return {Element} The switch.
 */
const ToggleSetting = memo(
	({ settingKey, label, help, checked, disabled, onChange }) => (
		<ToggleControl
			__nextHasNoMarginBottom
			label={label}
			help={help}
			checked={checked}
			disabled={disabled}
			onChange={(value) => onChange(settingKey, value)}
		/>
	)
);

/**
 * The settings page.
 *
 * @param {Object}      props               Component props.
 * @param {string}      props.option        The option name in the REST settings endpoint.
 * @param {Object[]}    props.sections      The groups the settings are shown in: key and title.
 * @param {Object[]}    props.fields        The settings: key, label, description, section and type
 *                                          ('toggle' or 'role').
 * @param {Object}      props.values        This site's own values.
 * @param {Object|null} props.networkValues The network default, on multisite.
 * @param {boolean}     props.usesNetwork   Whether this site follows the network default.
 * @param {boolean}     props.isMultisite   Whether this is a multisite network.
 * @param {Object[]}    props.roles         The roles a role setting can hold: value, label and group.
 * @param {Object}      props.roleGroups    The titles of the role groups, by group.
 * @param {string}      props.networkUrl    The network settings page, when the user may change it.
 * @param {boolean}     props.isNetwork     Whether this is the network settings page.
 * @param {string}      props.restPath      The REST path the settings are saved to.
 *
 * @return {Element} The settings page.
 */
const SettingsPage = ({
	option,
	sections,
	fields,
	values: initialValues,
	networkValues,
	usesNetwork,
	isMultisite,
	roles,
	roleGroups,
	networkUrl,
	isNetwork,
	restPath,
}) => {
	const [state, dispatch] = useReducer(reducer, {
		values: initialValues,
		useNetwork: usesNetwork,
		saved: { values: initialValues, useNetwork: usesNetwork },
		busy: false,
		notice: null,
	});
	const { values, useNetwork, saved, busy, notice } = state;
	const controller = useRef(null);

	// Abort a save still running when the page goes away.
	useEffect(() => () => controller.current?.abort(), []);

	const isDirty = useMemo(
		() =>
			useNetwork !== saved.useNetwork ||
			fields.some(
				({ key }) => !sameValue(values[key], saved.values[key])
			),
		[fields, values, useNetwork, saved]
	);

	// The settings per section, worked out once.
	const fieldsBySection = useMemo(
		() =>
			sections.map((section) => ({
				...section,
				fields: fields.filter((field) => field.section === section.key),
			})),
		[sections, fields]
	);

	const shown = useNetwork ? networkValues : values;

	const onChange = useCallback(
		(key, value) => dispatch({ type: 'CHANGE', key, value }),
		[]
	);
	const onUseNetwork = useCallback(
		(value) => dispatch({ type: 'SET_USE_NETWORK', useNetwork: value }),
		[]
	);
	const onDismiss = useCallback(
		() => dispatch({ type: 'DISMISS_NOTICE' }),
		[]
	);

	const onSave = useCallback(async () => {
		if (busy) {
			return;
		}

		controller.current?.abort();
		controller.current = new window.AbortController();
		const { signal } = controller.current;

		dispatch({ type: 'SAVE_START' });

		const data = { ...values };
		if (isMultisite) {
			data.use_network = useNetwork;
		}

		try {
			const response = await apiFetch({
				path: restPath,
				method: 'POST',
				data: { [option]: data },
				signal,
			});
			const stored = response?.[option] ?? data;
			const storedValues = Object.fromEntries(
				fields.map(({ key }) => [key, stored[key] ?? values[key]])
			);
			dispatch({
				type: 'SAVE_SUCCESS',
				values: storedValues,
				useNetwork: !!stored.use_network,
			});
		} catch (error) {
			if (signal.aborted) {
				return;
			}
			dispatch({
				type: 'SAVE_ERROR',
				message:
					error?.message ||
					__(
						'The settings could not be saved. Please try again.',
						'block-editor-templates'
					),
			});
		}
	}, [busy, values, useNetwork, isMultisite, option, restPath, fields]);

	return (
		<div className="abet-settings">
			<p className="abet-settings__intro">
				{isNetwork
					? __(
							'The default for every site in the network. A site administrator can choose to use different settings for their own site.',
							'block-editor-templates'
						)
					: __(
							'Choose who may create, edit, publish and delete templates, and which extra columns the template lists show.',
							'block-editor-templates'
						)}
			</p>

			{notice && (
				<Notice
					className="abet-settings__notice"
					status={notice.status}
					onRemove={onDismiss}
				>
					{notice.message}
				</Notice>
			)}

			<Card>
				{isMultisite && (
					<CardBody className="abet-settings__source">
						<ToggleControl
							__nextHasNoMarginBottom
							label={__(
								'Use the network settings',
								'block-editor-templates'
							)}
							help={
								useNetwork
									? __(
											'This site follows the defaults a network administrator sets. The settings below show them.',
											'block-editor-templates'
										)
									: __(
											'This site uses its own settings below.',
											'block-editor-templates'
										)
							}
							checked={useNetwork}
							onChange={onUseNetwork}
						/>
						{useNetwork && networkUrl && (
							<p className="abet-settings__network-link">
								<a href={networkUrl}>
									{__(
										'Change the network settings',
										'block-editor-templates'
									)}
								</a>
							</p>
						)}
					</CardBody>
				)}

				{fieldsBySection.map(
					({ key: section, title, fields: rows }, index) => (
						<Fragment key={section}>
							{(index > 0 || isMultisite) && <CardDivider />}
							<CardHeader isBorderless>
								<h2 className="abet-settings__title">
									{title}
								</h2>
							</CardHeader>
							<CardBody>
								<ul className="abet-settings__list">
									{rows.map(
										({ key, label, description, type }) => (
											<li
												key={key}
												className="abet-settings__item"
											>
												{type === 'role' ? (
													<RolePicker
														settingKey={key}
														label={label}
														help={description}
														value={shown[key]}
														roles={roles}
														groups={roleGroups}
														disabled={useNetwork}
														onChange={onChange}
													/>
												) : (
													<ToggleSetting
														settingKey={key}
														label={label}
														help={description}
														checked={!!shown[key]}
														disabled={useNetwork}
														onChange={onChange}
													/>
												)}
											</li>
										)
									)}
								</ul>
							</CardBody>
						</Fragment>
					)
				)}

				<CardFooter justify="flex-end">
					<Button
						variant="primary"
						isBusy={busy}
						disabled={busy || !isDirty}
						accessibleWhenDisabled
						onClick={onSave}
					>
						{busy
							? __('Saving…', 'block-editor-templates')
							: __('Save changes', 'block-editor-templates')}
					</Button>
				</CardFooter>
			</Card>
		</div>
	);
};

domReady(() => {
	const root = document.getElementById('abet-settings-root');
	const settings = window.abetSettings;
	if (!root || !settings) {
		return;
	}

	createRoot(root).render(<SettingsPage {...settings} />);
});
