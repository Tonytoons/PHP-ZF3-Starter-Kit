directory "#{release_path}/public/temp" do
  owner "www-data"
  group "www-data"
  mode "0777"
  action :create
  not_if do ::File.exists?("#{release_path}/public/temp") end
end
