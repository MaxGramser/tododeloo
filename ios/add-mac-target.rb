#!/usr/bin/env ruby
# Adds a native macOS app target ("TododelooMac") to the existing Xcode project.
# Pattern copied from tuneflow: shared core compiled into both targets, separate
# per-platform UI files. The iOS target (and its synchronized group) is left
# untouched; the Mac target uses explicit file references.
#
# Idempotent: re-running removes the previous Mac target/scheme first.
require 'xcodeproj'

project_path = File.expand_path('Tododeloo.xcodeproj', __dir__)
project = Xcodeproj::Project.open(project_path)

TARGET_NAME = 'TododelooMac'

# --- Clean any previous run -------------------------------------------------
project.targets.select { |t| t.name == TARGET_NAME }.each(&:remove_from_project)
if (grp = project.main_group['MacSources'])
  grp.remove_from_project
end

# --- Create the macOS application target ------------------------------------
mac = project.new_target(:application, TARGET_NAME, :osx, '14.0')

signing_ref = project.files.find { |f| f.path == 'Config/Signing.xcconfig' }

mac.build_configurations.each do |config|
  config.base_configuration_reference = signing_ref if signing_ref
  bs = config.build_settings
  bs['PRODUCT_BUNDLE_IDENTIFIER']            = 'com.tododeloo.mac'
  bs['PRODUCT_NAME']                         = '$(TARGET_NAME)'
  bs['SDKROOT']                              = 'macosx'
  bs['SUPPORTED_PLATFORMS']                  = 'macosx'
  bs['MACOSX_DEPLOYMENT_TARGET']             = '14.0'
  bs['CODE_SIGN_STYLE']                      = 'Automatic'
  bs['CODE_SIGN_ENTITLEMENTS']               = 'TododelooMac/TododelooMac.entitlements'
  bs['GENERATE_INFOPLIST_FILE']              = 'YES'
  bs['ENABLE_PREVIEWS']                      = 'YES'
  bs['SWIFT_VERSION']                        = '5.0'
  bs['SWIFT_EMIT_LOC_STRINGS']               = 'YES'
  bs['MARKETING_VERSION']                    = '1.0'
  bs['CURRENT_PROJECT_VERSION']              = '1'
  bs['INFOPLIST_KEY_CFBundleDisplayName']    = 'Tododeloo'
  bs['INFOPLIST_KEY_LSApplicationCategoryType'] = 'public.app-category.productivity'
  bs['LD_RUNPATH_SEARCH_PATHS']              = ['$(inherited)', '@executable_path/../Frameworks']
  bs['COMBINE_HIDPI_IMAGES']                 = 'YES'
  bs['ASSETCATALOG_COMPILER_APPICON_NAME']   = 'AppIcon'
end

# --- Source files: shared core + macOS UI -----------------------------------
shared = %w[
  Tododeloo/Models/Models.swift
  Tododeloo/Networking/APIClient.swift
  Tododeloo/Networking/APIError.swift
  Tododeloo/Networking/Keychain.swift
  Tododeloo/State/Session.swift
  Tododeloo/State/BoardModel.swift
  Tododeloo/State/ListsModel.swift
  Tododeloo/Support/AppConfig.swift
  Tododeloo/Support/Theme.swift
  Tododeloo/Views/Components.swift
]
mac_ui = Dir.chdir(__dir__) { Dir.glob('TododelooMac/*.swift') }.sort

group = project.main_group.new_group('MacSources')
refs = (shared + mac_ui).map { |path| group.new_reference(path) }
mac.add_file_references(refs)

# App icon / asset catalog (macOS) as a resource.
assets = group.new_reference('TododelooMac/Assets.xcassets')
mac.resources_build_phase.add_file_reference(assets)

project.save

# --- Shared scheme ----------------------------------------------------------
scheme = Xcodeproj::XCScheme.new
scheme.add_build_target(mac)
scheme.set_launch_target(mac)
scheme.save_as(project_path, TARGET_NAME, true)

puts "Added target '#{TARGET_NAME}' with #{refs.length} source files."
