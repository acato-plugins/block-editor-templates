/**
 * Block Templates > Settings: which extra columns the template lists show.
 *
 * Replaces the plain Settings API form that the PHP side (Settings::render_site_page) prints as the
 * fallback without JavaScript, and saves through the REST settings endpoint.
 */

/**
 * Import WordPress dependencies.
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	CardHeader,
	Notice,
	ToggleControl,
} from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import {
	createRoot,
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import './settings.scss';

/**
 * The settings page.
 *
 * @param {Object}      props               Component props.
 * @param {string}      props.option        The option name in the REST settings endpoint.
 * @param {Object[]}    props.fields        The settings: key, label and description.
 * @param {Object}      props.values        This site's own values.
 * @param {Object|null} props.networkValues The network default, on multisite.
 * @param {boolean}     props.usesNetwork   Whether this site follows the network default.
 * @param {boolean}     props.isMultisite   Whether this is a multisite network.
 *
 * @return {Element} The settings page.
 */
const SettingsPage = ({
	option,
	fields,
	values: initialValues,
	networkValues,
	usesNetwork,
	isMultisite,
}) => {
	const [values, setValues] = useState(initialValues);
	const [useNetwork, setUseNetwork] = useState(usesNetwork);
	const [saved, setSaved] = useState({
		values: initialValues,
		useNetwork: usesNetwork,
	});
	const [busy, setBusy] = useState(false);
	const [notice, setNotice] = useState(null);
	const controller = useRef(null);

	// Abort a save still running when the page goes away.
	useEffect(() => () => controller.current?.abort(), []);

	const isDirty = useMemo(
		() =>
			useNetwork !== saved.useNetwork ||
			fields.some(({ key }) => values[key] !== saved.values[key]),
		[fields, values, useNetwork, saved]
	);

	const shown = useNetwork ? networkValues : values;

	const onToggle = useCallback(
		(key, checked) =>
			setValues((current) => ({ ...current, [key]: checked })),
		[]
	);

	const onSave = useCallback(async () => {
		if (busy) {
			return;
		}

		controller.current?.abort();
		controller.current = new window.AbortController();
		const { signal } = controller.current;

		setBusy(true);
		setNotice(null);

		const data = { ...values };
		if (isMultisite) {
			data.use_network = useNetwork;
		}

		try {
			const response = await apiFetch({
				path: '/wp/v2/settings',
				method: 'POST',
				data: { [option]: data },
				signal,
			});
			const stored = response?.[option] ?? data;
			setSaved({
				values: { ...values },
				useNetwork: !!stored.use_network,
			});
			setNotice({
				status: 'success',
				message: __('Settings saved.', 'block-editor-templates'),
			});
		} catch (error) {
			if (signal.aborted) {
				return;
			}
			setNotice({
				status: 'error',
				message:
					error?.message ||
					__(
						'The settings could not be saved. Please try again.',
						'block-editor-templates'
					),
			});
		} finally {
			if (!signal.aborted) {
				setBusy(false);
			}
		}
	}, [busy, values, useNetwork, isMultisite, option]);

	return (
		<div className="abet-settings">
			<p className="abet-settings__intro">
				{__(
					'Choose which extra columns the template lists show. They appear on the Post Type, Post Type Archive, Taxonomy Archive and Special Templates lists.',
					'block-editor-templates'
				)}
			</p>

			{notice && (
				<Notice
					className="abet-settings__notice"
					status={notice.status}
					onRemove={() => setNotice(null)}
				>
					{notice.message}
				</Notice>
			)}

			<Card>
				<CardHeader>
					<h2 className="abet-settings__title">
						{__('Template lists', 'block-editor-templates')}
					</h2>
				</CardHeader>

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
											'This site follows the defaults a network administrator sets. The switches below show them.',
											'block-editor-templates'
										)
									: __(
											'This site uses its own settings below.',
											'block-editor-templates'
										)
							}
							checked={useNetwork}
							onChange={setUseNetwork}
						/>
					</CardBody>
				)}

				<CardBody>
					<ul className="abet-settings__list">
						{fields.map(({ key, label, description }) => (
							<li key={key} className="abet-settings__item">
								<ToggleControl
									__nextHasNoMarginBottom
									label={label}
									help={description}
									checked={!!shown[key]}
									disabled={useNetwork}
									onChange={(checked) =>
										onToggle(key, checked)
									}
								/>
							</li>
						))}
					</ul>
				</CardBody>

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
