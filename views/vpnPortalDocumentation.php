<?php $this->layout('base', ['activeItem' => 'documentation']); ?>
<?php $this->start('content'); ?>
    <h2>Getting Started</h2>
    <p>
        Follow these steps:
    </p>

    <ol>
        <li>Choose and download a VPN application from the list below;</li>
        <li>Load the configuration file, obtained <a href="new">here</a>, in 
            the application.</li>
    </ol>

    <h2>Application</h2>
    <h3>Windows</h3>

        <ol>
        <li>Download the <a target="_blank" href="https://openvpn.net/index.php/open-source/downloads.html">OpenVPN Community client</a>
            <ul>
                <li>Choose "Installer, Windows Vista and later";
                <li>Make sure you have the installer from the 2.4 release, e.g. <code>openvpn-install-2.4.7-I603.exe</code>;</li>
                <li>Keep your version updated, there may be (security) releases from time to time!</li>
            </ul>
        </li>
        <li>Install the OpenVPN Community client</li>
        <li>(Optionally) read the documentation <a target="_blank" href="https://github.com/OpenVPN/openvpn-gui/">here</a>;</li>
        <li>Start OpenVPN (a Desktop icon is created automatically);
        <li>Import the downloaded configuration by right clicking on OpenVPN's tray icon and choosing "Import".
        </ol>

    <p>
        <em>Note</em>: OpenVPN will automatically start on Windows start-up, it will <em>not</em> automatically connect!
    </p>

    <h3>macOS</h3>
    <p>
        Download <a target="_blank" href="https://tunnelblick.net/">tunnelblick</a>. 
        Make sure you use OpenVPN 2.4 in tunnelblick! You can modify this in the settings if required.
        Read the <a target="_blank" href="https://tunnelblick.net/czQuick.html">Quick Start Guide</a>.
    </p>

    <h3>Android</h3>
        Install <a target="_blank" href="https://play.google.com/store/apps/details?id=de.blinkt.openvpn">OpenVPN for Android</a>, 
        also available via <a target="_blank" href="https://f-droid.org/repository/browse/?fdid=de.blinkt.openvpn">F-Droid</a>. 
        The proprietary <a target="_blank" href="https://play.google.com/store/apps/details?id=net.openvpn.openvpn">OpenVPN Connect</a> can also be used. See the OpenVPN Connect <a target="_blank" href="https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-android-faq.html">FAQ</a>.

    <h3>iOS</h3>
    <p>
        Install <a target="_blank" href="https://itunes.apple.com/us/app/openvpn-connect/id590379981">OpenVPN Connect</a>. 
        A <a target="_blank" href="https://docs.openvpn.net/docs/openvpn-connect/openvpn-connect-ios-faq.html">FAQ</a> is available.
    </p>
    <p>
        You may want to enable <code>Seamless tunnel (iOS8+)</code> in the OpenVPN Settings. 
        It will try to keep the VPN tunnel active as much as possible. See the FAQ for more details.
    </p>
     
    <h3>Linux</h3>
    <p>
        Importing the VPN configuration in NetworkManager works fine on modern 
        Linux distributions. The following distributions were tested and work:
    </p>
    <ul>
        <li><a href="https://getfedora.org/">Fedora</a> >= 28;</li>
        <li><a href="https://www.centos.org/">CentOS</a> >= 7 (<code>yum install NetworkManager-openvpn-gnome</code>);</li>
        <li><a href="https://www.debian.org/">Debian</a> >= 9 (<code>apt install network-manager-openvpn-gnome</code>);</li>
        <li><a href="https://www.ubuntu.com/">Ubuntu</a> >= 18.04 LTS (<code>apt install network-manager-openvpn-gnome</code>);</li>
    </ul>

    <?php if (0 !== count($twoFactorMethods)): ?>
        <h2 id="2fa">Two-factor Authentication</h2>
        <p>
            Two-factor authentication (2FA) can be used to protect your account from 
            unauthorized access. It works by asking for an additional <em>key</em>
            that needs to be provided on every login to the portal.
        </p>    
        <p>
            Enroll for 2FA <a href="two_factor_enroll">here</a>.
        </p>

        <?php if (in_array('totp', $twoFactorMethods, true)): ?>
            <h3>TOTP</h3> 
            <p>
                The TOTP method works by registering a <em>secret</em> in an 
                application, e.g. running on your mobile phone. There are various 
                applications to choose from, but at the moment we recommend FreeOTP.
            </p>
            
            <ul>
                <li>Android (<a target="_blank" href="https://play.google.com/store/apps/details?id=org.fedorahosted.freeotp">Google Play Store</a>, <a target="_blank" href="https://f-droid.org/repository/browse/?fdid=org.fedorahosted.freeotp">F-Droid</a>)</li>
                <li>iOS (<a target="_blank" href="https://itunes.apple.com/us/app/freeotp-authenticator/id872559395">iTunes</a>)</li>

            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Recommendations for using the VPN</h2>
    <p>
        Install the latest version of 
        <a href="https://getfirefox.com">Mozilla Firefox</a> to browse the 
        web, and install ONLY the following extensions. Remove ALL other 
        installed extensions!
    </p>

    <ul>
        <li>
            <a href="https://addons.mozilla.org/en-US/firefox/addon/ublock-origin/">uBlock Origin</a>: 
            "An efficient blocker: easy on memory and CPU footprint, and yet can 
            load and enforce thousands more filters than other popular blockers 
            out there."
        </li>
        <li>
            <a href="https://addons.mozilla.org/en-US/firefox/addon/https-everywhere/">HTTPS Everywhere</a>: 
            "...protect your communications by enabling HTTPS encryption 
            automatically on sites that are known to support it, even when you 
            type URLs or follow links that omit the https: prefix."
        </li>
        <li>
            <a href="https://addons.mozilla.org/en-US/firefox/addon/noscript/">NoScript Security Suite</a> (<em>Advanced Users</em>): 
            "Allow active content to run only from sites you trust, and protect 
            yourself against XSS and Clickjacking attacks, "Spectre", 
            "Meltdown" and other JavaScript exploits."
        </li>
    </ul>

    <p>
        Make sure you <em>disable</em> WebRTC to avoid leaking your (local)
        non-VPN addresses by typing <code>about:config</code> in the URL bar, 
        looking for the <code>media.peerconnection.enabled</code> key and 
        setting it to <code>false</code>.
    </p>

    <h2>Privacy and Trust</h2>
    <p>
        Using a VPN service is not a magical solution to remain safe and 
        anonymous when using the Internet, nor should you use this service 
        under the assumption you can never be traced back. This is not only 
        true for this VPN service, but for all of them. Better safe than sorry!
    </p>
    <p>
        If you want to obtain a higher degree of anonymity and security you 
        should consider using software like the 
        <a target="_blank" href="https://www.torproject.org/">Tor Browser</a>, 
        or preferably <a target="_blank" href="https://tails.boum.org/">Tails</a>. 
        Even then, caution is required. Please make sure you read all 
        accompanying documentation and warnings there!
    </p>
    <p>
        Most smartphones and computers will use the Internet connection 
        before the VPN can be activated. On smartphones this problem can only
        be solved by <em>rooting</em> or <em>jail breaking</em> your phone and
        installing applications on it that tightly control the network traffic. 
        This is <strong>NOT</strong> recommended! On a computer this can be 
        dealt with by using Tails mentioned above.
    </p>
<?php $this->stop('content'); ?>
