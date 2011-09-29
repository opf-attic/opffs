

all:
	@echo "opffs does not need to be compiled. Nothing to do. "
	@echo "To install, use: make install"

configure:

install:
	mkdir -p $(DESTDIR) 2>/dev/null
	cp opffs $(DESTDIR)
	cp process_results.php $(DESTDIR)
