%global github_owner     fkooman
%global github_name      vpn-user-portal

Name:       vpn-user-portal
Version:    0.1.9
Release:    1%{?dist}
Summary:    Portal to manage OpenVPN client configurations

Group:      Applications/Internet
License:    ASL-2.0
URL:        https://github.com/%{github_owner}/%{github_name}
Source0:    https://github.com/%{github_owner}/%{github_name}/archive/%{version}.tar.gz
Source1:    vpn-user-portal-httpd-conf
Source2:    vpn-user-portal-autoload.php

BuildArch:  noarch

Requires:   php >= 5.4
Requires:   php-openssl
Requires:   php-pdo
Requires:   httpd

Requires:   php-composer(fkooman/ini) >= 0.2.0
Requires:   php-composer(fkooman/ini) < 0.3.0
Requires:   php-composer(fkooman/rest) >= 0.6.1
Requires:   php-composer(fkooman/rest) < 0.7.0
Requires:   php-composer(fkooman/rest-plugin-mellon) >= 0.1.0
Requires:   php-composer(fkooman/rest-plugin-mellon) < 0.2.0

Requires:   php-pear(pear.twig-project.org/Twig) >= 1.15
Requires:   php-pear(pear.twig-project.org/Twig) < 2.0

Requires:   php-composer(guzzlehttp/guzzle) >= 4.0
Requires:   php-composer(guzzlehttp/guzzle) < 5.0
Requires:   php-composer(guzzlehttp/streams) >= 1.0
Requires:   php-composer(guzzlehttp/streams) < 2.0

#Starting F21 we can use the composer dependency for Symfony
#Requires:   php-composer(symfony/classloader) >= 2.3.9
#Requires:   php-composer(symfony/classloader) < 3.0
Requires:   php-pear(pear.symfony.com/ClassLoader) >= 2.3.9
Requires:   php-pear(pear.symfony.com/ClassLoader) < 3.0

Requires(post): policycoreutils-python
Requires(postun): policycoreutils-python

%description
This project provides a user interface for managing OpenVPN configurations 
using the vpn-cert-service software.

%prep
%setup -qn %{github_name}-%{version}

sed -i "s|dirname(__DIR__)|'%{_datadir}/vpn-user-portal'|" bin/vpn-user-portal-init

%build

%install
# Apache configuration
install -m 0644 -D -p %{SOURCE1} ${RPM_BUILD_ROOT}%{_sysconfdir}/httpd/conf.d/vpn-user-portal.conf

# Application
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal
cp -pr web views src ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal

# use our own class loader
mkdir -p ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal/vendor
cp -pr %{SOURCE2} ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal/vendor/autoload.php

mkdir -p ${RPM_BUILD_ROOT}%{_bindir}
cp -pr bin/* ${RPM_BUILD_ROOT}%{_bindir}

# Config
mkdir -p ${RPM_BUILD_ROOT}%{_sysconfdir}/vpn-user-portal
cp -p config/config.ini.defaults ${RPM_BUILD_ROOT}%{_sysconfdir}/vpn-user-portal/config.ini
ln -s ../../../etc/vpn-user-portal ${RPM_BUILD_ROOT}%{_datadir}/vpn-user-portal/config

# Data
mkdir -p ${RPM_BUILD_ROOT}%{_localstatedir}/lib/vpn-user-portal

%post
semanage fcontext -a -t httpd_sys_rw_content_t '%{_localstatedir}/lib/vpn-user-portal(/.*)?' 2>/dev/null || :
restorecon -R %{_localstatedir}/lib/vpn-user-portal || :

%postun
if [ $1 -eq 0 ] ; then  # final removal
semanage fcontext -d -t httpd_sys_rw_content_t '%{_localstatedir}/lib/vpn-user-portal(/.*)?' 2>/dev/null || :
fi

%files
%defattr(-,root,root,-)
%config(noreplace) %{_sysconfdir}/httpd/conf.d/vpn-user-portal.conf
%dir %attr(-,apache,apache) %{_sysconfdir}/vpn-user-portal
%config(noreplace) %attr(0600,apache,apache) %{_sysconfdir}/vpn-user-portal/config.ini
%{_bindir}/vpn-user-portal-init
%dir %{_datadir}/vpn-user-portal
%{_datadir}/vpn-user-portal/src
%{_datadir}/vpn-user-portal/vendor
%{_datadir}/vpn-user-portal/web
%{_datadir}/vpn-user-portal/views
%{_datadir}/vpn-user-portal/config
%dir %attr(0700,apache,apache) %{_localstatedir}/lib/vpn-user-portal
%doc README.md COPYING composer.json config/config.ini.defaults

%changelog
* Sat Oct 25 2014 François Kooman <fkooman@tuxed.net> - 0.1.9-1
- update to 0.1.9

* Sat Oct 25 2014 François Kooman <fkooman@tuxed.net> - 0.1.8-1
- update to 0.1.8
- set config file permissions to apache user only

* Sat Oct 25 2014 François Kooman <fkooman@tuxed.net> - 0.1.7-2
- config file owned and only readable by apache user now

* Thu Oct 23 2014 François Kooman <fkooman@tuxed.net> - 0.1.7-1
- update to 0.1.7

* Thu Oct 23 2014 François Kooman <fkooman@tuxed.net> - 0.1.6-1
- update to 0.1.6

* Wed Oct 22 2014 François Kooman <fkooman@tuxed.net> - 0.1.5-1
- update to 0.1.5
