#!/usr/bin/env bash
# Starts an Android emulator, installs an APK on it and opens the app.
# Used by .github/workflows/check-apk.yml; each command is one step there.
#
#   apk-on-emulator.sh boot                       start the emulator ($IMAGE) and wait until it is up
#   apk-on-emulator.sh first-install <apk>        install on a phone that never had the app, then open it
#   apk-on-emulator.sh update <published> <apk>   install the published app, then <apk> over it, then open it
#
# PKG is the app id. Screenshots, the text on the screen and the phone's log go to $OUT.
set -euo pipefail

OUT="${OUT:-$PWD/emulator-output}"
mkdir -p "$OUT"
if [ -n "${ANDROID_HOME:-}" ]; then
	export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/emulator:$PATH"
fi

warn() { echo "::warning::$*"; }
prop() { adb shell getprop "$1" 2> /dev/null | tr -d '\r'; }

boot() {
	: "${ANDROID_HOME:?no Android SDK on this runner}" "${IMAGE:?IMAGE is not set}"
	local sdkmanager avdmanager waited=0

	sdkmanager=$(find "$ANDROID_HOME/cmdline-tools" -type f -name sdkmanager | sort | tail -1)
	if [ -z "$sdkmanager" ]; then
		echo "::error::sdkmanager not found under $ANDROID_HOME/cmdline-tools"
		return 1
	fi
	avdmanager="$(dirname "$sdkmanager")/avdmanager"

	yes 2> /dev/null | "$sdkmanager" --licenses > /dev/null 2>&1 || true
	if ! "$sdkmanager" --install emulator platform-tools "$IMAGE" > "$OUT/sdkmanager.txt" 2>&1; then
		tail -30 "$OUT/sdkmanager.txt"
		echo "::error::Could not install the emulator or $IMAGE"
		return 1
	fi

	# Said out loud, because avdmanager and the emulator do not agree on the default place.
	export ANDROID_AVD_HOME="$HOME/.android/avd"
	mkdir -p "$ANDROID_AVD_HOME"
	echo no | "$avdmanager" create avd --force --name check --package "$IMAGE" --device pixel_6 > /dev/null
	# Room for two installs of a 120 MB app.
	printf 'disk.dataPartition.size=6G\nhw.ramSize=4096\nhw.cpu.ncore=4\n' >> "$ANDROID_AVD_HOME/check.avd/config.ini"

	nohup "$ANDROID_HOME/emulator/emulator" -avd check -no-window -gpu swiftshader_indirect -no-snapshot -noaudio -no-boot-anim \
		> "$OUT/emulator.txt" 2>&1 &

	if ! timeout 300 adb wait-for-device; then
		tail -30 "$OUT/emulator.txt"
		echo "::error::The emulator did not appear"
		return 1
	fi
	until [ "$(prop sys.boot_completed)" = "1" ]; do
		sleep 5
		waited=$((waited + 5))
		if [ "$waited" -ge 900 ]; then
			tail -30 "$OUT/emulator.txt"
			echo "::error::The emulator did not start within 15 minutes"
			return 1
		fi
	done
	adb shell input keyevent 82 > /dev/null 2>&1 || true
	# A slow emulator shows "Pixel Launcher isn't responding" over whatever is open. The system closes
	# such an app quietly instead; an app of ours that stops responding is found in the log (see stuck).
	adb shell settings put global hide_error_dialogs 1 > /dev/null 2>&1 || true
	for scale in window_animation_scale transition_animation_scale animator_duration_scale; do
		adb shell settings put global "$scale" 0 > /dev/null 2>&1 || true
	done
	# The first minute after the start is the busiest; let it pass before anything is measured.
	sleep 45
	echo "Up after ${waited}s: Android $(prop ro.build.version.release), API $(prop ro.build.version.sdk), $(prop ro.product.cpu.abilist)"
}

# install_apk <file> [adb install flags]: prints the phone's answer; on a refusal also what it logged.
install_apk() {
	local file=$1 answer
	shift
	answer=$(adb install "$@" "$file" 2>&1) || true
	echo "$answer"
	if ! grep -q '^Success' <<< "$answer"; then
		echo "--- what the phone logged ---"
		adb logcat -d -t 3000 2> /dev/null \
			| grep -E 'PackageManager|PackageInstaller|NativeLibraryHelper|ApkSignatureVerifier|installd' | tail -40 || true
		return 1
	fi
}

crashed() {
	local log
	log=$(adb logcat -d -b crash 2> /dev/null || true)
	grep -qE "Process: $PKG|>>> $PKG <<<" <<< "$log"
}

stuck() {
	local log
	log=$(adb logcat -d -s ActivityManager:E 2> /dev/null || true)
	grep -q "ANR in $PKG" <<< "$log"
}

# Prints the text the app shows. Fails when the screen could not be read.
screen_texts() {
	adb shell rm -f /sdcard/window.xml > /dev/null 2>&1 || true
	rm -f "$OUT/wait-button.txt"
	adb shell uiautomator dump /sdcard/window.xml > /dev/null 2>&1 || return 1
	adb exec-out cat /sdcard/window.xml > "$OUT/window.xml" 2> /dev/null || return 1
	[ -s "$OUT/window.xml" ] || return 1
	python3 - "$OUT/window.xml" "$PKG" "$OUT/wait-button.txt" << 'PY'
import re
import sys
import xml.etree.ElementTree as ET

try:
    root = ET.parse(sys.argv[1]).getroot()
except ET.ParseError:
    sys.exit(1)
seen = []
wait_button = ''
for node in root.iter('node'):
    if node.get('package') != sys.argv[2]:
        if node.get('package') == 'android' and node.get('text') == 'Wait':
            left, top, right, bottom = map(int, re.findall(r'\d+', node.get('bounds') or '0 0 0 0'))
            wait_button = '%d %d' % ((left + right) // 2, (top + bottom) // 2)
        continue
    for key in ('text', 'content-desc'):
        value = ' '.join((node.get(key) or '').split())
        if value and value not in seen:
            seen.append(value)
with open(sys.argv[3], 'w') as out:
    out.write(wait_button)
print('\n'.join(seen))
PY
}

# open_app <label>: opens the app and waits for its first screen. Fails when the app crashed, is not
# on screen, or shows no text (an app stuck on its splash screen shows none).
open_app() {
	local label=$1 texts="" tries=0 focus pid x y

	adb logcat -c > /dev/null 2>&1 || true
	adb shell monkey -p "$PKG" -c android.intent.category.LAUNCHER 1 > /dev/null 2>&1 || true
	while [ "$tries" -lt 30 ]; do
		sleep 5
		tries=$((tries + 1))
		if crashed || stuck; then
			break
		fi
		if texts=$(screen_texts) && [ -n "$texts" ]; then
			break
		fi
		# A system question in front of the app (for example "Allow notifications?"): answer no, look again.
		if grep -q 'permissioncontroller' "$OUT/window.xml" 2> /dev/null; then
			adb shell input keyevent 4 > /dev/null 2>&1 || true
		fi
		# Another app's "isn't responding" window in front of ours: choose "Wait", look again.
		if [ -s "$OUT/wait-button.txt" ]; then
			read -r x y < "$OUT/wait-button.txt" || true
			adb shell input tap "$x" "$y" > /dev/null 2>&1 || true
		fi
	done

	adb exec-out screencap -p > "$OUT/$label.png" 2> /dev/null || true
	adb logcat -d > "$OUT/$label-log.txt" 2> /dev/null || true
	if [ -f "$OUT/window.xml" ]; then
		cp "$OUT/window.xml" "$OUT/$label-window.xml"
	fi

	if crashed; then
		adb logcat -d -b crash 2> /dev/null | tail -60 || true
		echo "::error::$label: the app crashed"
		return 1
	fi
	if stuck; then
		echo "::error::$label: the app stopped responding"
		return 1
	fi
	pid=$(adb shell pidof "$PKG" 2> /dev/null | tr -d '\r' || true)
	if [ -z "$pid" ]; then
		echo "::error::$label: the app is not running"
		return 1
	fi
	focus=$(adb shell dumpsys activity activities 2> /dev/null | grep -E 'ResumedActivity' | head -3 || true)
	if ! grep -q "$PKG/" <<< "$focus"; then
		echo "$focus"
		echo "::error::$label: the app is not on screen"
		return 1
	fi
	if [ -z "$texts" ]; then
		echo "::error::$label: the app is open but shows no text after $((tries * 5)) seconds"
		return 1
	fi
	echo "$label: open after about $((tries * 5)) seconds. Text on the screen:"
	sed 's/^/    /' <<< "$texts" | head -40 || true
}

installed_version() {
	adb shell dumpsys package "$PKG" 2> /dev/null | grep -E 'versionName=|versionCode=' | head -2 | tr -d '\r' | xargs || true
}

first_install() {
	adb uninstall "$PKG" > /dev/null 2>&1 || true
	echo "== A phone that never had the app =="
	if ! install_apk "$1"; then
		echo "::error::A first install of this file failed"
		return 1
	fi
	echo "Installed: $(installed_version)"
	open_app first-install
}

update() {
	adb uninstall "$PKG" > /dev/null 2>&1 || true
	echo "== The published app first =="
	if ! install_apk "$1"; then
		warn "The published APK does not install on this Android version, so there is no update to test here."
		return 0
	fi
	echo "Installed: $(installed_version)"
	open_app published || warn "The published app did not open cleanly on this Android version."
	adb shell am force-stop "$PKG" > /dev/null 2>&1 || true

	echo "== This file over it, the way a member updates =="
	if ! install_apk "$2" -r; then
		echo "::error::The update over the published app failed"
		return 1
	fi
	echo "Installed: $(installed_version)"
	open_app after-update
}

case "${1:-}" in
	boot)
		boot
		;;
	first-install)
		: "${PKG:?PKG is not set}"
		first_install "${2:?apk file}"
		;;
	update)
		: "${PKG:?PKG is not set}"
		update "${2:?published apk}" "${3:?apk file}"
		;;
	*)
		echo "usage: $0 boot | first-install <apk> | update <published apk> <apk>" >&2
		exit 2
		;;
esac
