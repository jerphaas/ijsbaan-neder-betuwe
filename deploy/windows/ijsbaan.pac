function FindProxyForURL(url, host) {
    host = host.toLowerCase().replace(/\.$/, "");
    if (host === "ijsbaannederbetuwe.nl" || host === "www.ijsbaannederbetuwe.nl" ||
        host === "treecentreopheusden.nl" || host === "www.treecentreopheusden.nl") {
        return "PROXY 127.0.0.1:40000; DIRECT";
    }
    return "DIRECT";
}
